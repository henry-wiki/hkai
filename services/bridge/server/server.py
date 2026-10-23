#!/usr/bin/env python3
"""
server.py — HTTP 伺服器 singleton（FastAPI + uvicorn）。

職責：
- initialize(config)：注入設定
- start()：啟動 uvicorn（阻塞；bind 預檢＋啟動結果回報）
- _lifespan(app)：FastAPI lifespan context（就緒即寫 running）
- _register_routes(app)：掛載所有 router
- _heartbeat_loop()：背景心跳任務（每秒寫一次統一心跳 process_status/*.json）
- sc_http.port_available()：開機前 bind 預檢（失敗即帶原因寫 failed 退出）
- _write_server_status()：寫啟動結果檔（storage_dir/bridge/server_status.json——PHP 讀）

使用方式：
    from .server.server import server_class
    server_class.get_instance().initialize(config)
    server_class.get_instance().start()
"""

import asyncio
import json
import os
import time
from contextlib import asynccontextmanager
from pathlib import Path

import lifecycle_guard
import uvicorn
from fastapi import FastAPI

from .. import context
from ..runtime import activity, heartbeat, process_list, sc_logger
from ..utils import sc_http


class server_class:
    """
    HTTP 伺服器（singleton——整個程序只有一個實例）。
    """

    _instance: "server_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "server_class":
        """取得 server_class 單例。"""
        return cls()

    def __init__(self) -> None:
        if getattr(self, "_initialized", False):
            return
        self._initialized = True
        self.config = None

    def initialize(self, config: dict) -> None:
        """注入設定（由 main.py 呼叫）。"""
        self.config = config

    def start(self) -> None:
        """啟動 uvicorn（阻塞直到伺服器停止）。"""
        if self.config is None:
            sc_logger.log("server.start() called before initialize()")
            return

        server_settings = self.config["server_settings"]

        sc_logger.log(f"server starting (host={server_settings['host']} port={server_settings['port']})")

        # 台帳（append-only JSONL）：server 自身啟動——本程序即 uvicorn 伺服器（非子程序）
        process_list.append_spawn(
            pid=os.getpid(),
            ppid=os.getppid(),
        )

        # bind 預檢（試 bind 一次即放——唔等 uvicorn sys.exit；失敗即寫 failed 退出）
        port_check = sc_http.port_available(
            server_settings["host"],
            server_settings["port"],
        )

        # 判定：port 唔可用 → 寫失敗結果檔、退出（PHP 拉起端讀檔即知失敗＋原因）
        if port_check.is_available is False:
            sc_logger.log(f"server start failed: {port_check.message}")
            self._write_server_status("failed", port_check.message)
            return

        app = FastAPI(lifespan=self._lifespan)
        app.state.config = self.config

        # 活動追蹤（每個 HTTP 請求＝仲有人用；閒置過久＋無串流 → 自我退出）
        @app.middleware("http")
        async def track_activity(request, call_next):
            activity.touch()
            return await call_next(request)

        self._register_routes(app)

        # 判定：uvicorn 途中先失敗（罕見——預檢已過）→ 寫失敗結果檔再拋
        try:
            uvicorn.run(
                app,
                host=server_settings["host"],
                port=server_settings["port"],
                log_config=None,
                access_log=False,
            )
        except BaseException as start_error:
            self._write_server_status("failed", self._format_start_error(start_error))
            raise

    @asynccontextmanager
    async def _lifespan(self, app: FastAPI):
        """啟動／關閉掛載點（背景心跳任務＋就緒後寫啟動結果）。"""
        heartbeat_task = asyncio.create_task(self._heartbeat_loop())

        # 心跳：lifespan 就緒（伺服器真係起到）才開始——起唔到就冇心跳
        from . import server_stream

        heartbeat.start(
            interval_sec    =1.0,
            idle_exit_sec   =context.IDLE_EXIT_SEC,
            is_busy         =lambda: server_stream.get_active_stream_count() > 0,
        )

        # 啟動成功（bind 已過、lifespan 就緒）→ 寫 running（PHP 拉起後嘅成功判準）
        self._write_server_status("running")

        yield

        # 正常關閉：收心跳執行緒＋清活動檔（唔留殘檔）
        heartbeat.stop()
        activity.clear()
        heartbeat_task.cancel()

    async def _heartbeat_loop(self) -> None:
        """背景監控任務（runtime 檔被刪／BUILD 變更 → 自我退出；心跳由 heartbeat 執行緒負責）。"""
        runtime_config_path = str((self.config or {}).get("runtime_config_path", ""))
        build_watch = lifecycle_guard.build_watch_class(lifecycle_guard.find_site_root(runtime_config_path))

        while True:
            # 判定：runtime.json 已被刪（建置／清理）→ 自我退出，不留孤兒心跳檔
            if runtime_config_path != "" and Path(runtime_config_path).is_file() is False:
                sc_logger.log("runtime config removed, bridge exiting")
                heartbeat.stop()
                activity.clear()
                os._exit(0)

            # 判定：BUILD 已變（站台已重新發佈）→ 記錄後自我退出，等新版本再拉起
            build_watch_result = build_watch.check()
            if build_watch_result["changed"] is True:
                sc_logger.log("BUILD changed " + build_watch_result["previous"] + " -> " + build_watch_result["current"] + ", bridge exiting")
                heartbeat.stop()
                activity.clear()
                os._exit(0)

            await asyncio.sleep(1)

    def _write_server_status(self, status: str, reason: str = "") -> None:
        """寫啟動結果檔（storage_dir/bridge/server_status.json——PHP 拉起後輪詢讀取）。"""
        try:
            status_dir = Path(self.config["storage_dir"]) / self.config.get("folders", {}).get("bridge", "bridge")
            status_dir.mkdir(parents=True, exist_ok=True)

            status_file_name = self.config.get("filenames", {}).get("server_status", "server_status.json")
            status_path = status_dir / status_file_name

            record = {
                "status": status,
                "reason": reason,
                "pid":    os.getpid(),
                "host":   self.config["server_settings"]["host"],
                "port":   self.config["server_settings"]["port"],
                "at":     int(time.time()),
            }

            content = json.dumps(record, indent=2, ensure_ascii=False)
            tmp_path = Path(str(status_path) + ".tmp")
            tmp_path.write_text(content, encoding="utf-8", newline="\n")
            os.replace(tmp_path, status_path)
        except Exception:  # noqa: BLE001
            pass  # 結果檔寫入失敗絕不影響主流程（PHP 端有逾時兜底）

    def _format_start_error(self, start_error: BaseException) -> str:
        """把啟動錯誤砌成人讀原因（OSError 帶 errno 標準格式；其餘型別＋內容）。"""
        # 判定：SystemExit（uvicorn 內部啟動失敗——真正原因喺 audit.log）
        if isinstance(start_error, SystemExit):
            return f"server startup failed (exit code {start_error.code})"

        # 判定：OSError（bind 失敗等）→ 標準 [Errno] 格式
        if isinstance(start_error, OSError):
            # 判定：有 errno → 標準格式
            if start_error.errno is not None:
                return f"[Errno {start_error.errno}] {start_error.strerror}"
            return str(start_error)

        return f"{type(start_error).__name__}: {start_error}"

    def _register_routes(self, app: FastAPI) -> None:
        """掛載所有 router。"""
        from .server_daemon import router as daemon_router
        from .server_diagnostics import router as diagnostics_router
        from .server_process import router as process_router
        from .server_projects import router as projects_router
        from .server_stream import build_router as build_stream_router

        app.include_router(daemon_router)
        app.include_router(diagnostics_router)
        app.include_router(process_router)
        app.include_router(projects_router)
        app.include_router(build_stream_router())
