#!/usr/bin/env python3
"""
server.py — HTTP 伺服器 singleton（FastAPI + uvicorn）。

職責：
- initialize(config)：注入設定
- start()：啟動 uvicorn（阻塞；bind 預檢＋啟動結果回報）
- _lifespan(app)：FastAPI lifespan context（就緒即寫 running）
- _register_routes(app)：掛載所有 router
- _prepare_logging()：uvicorn logger 導向 audit.log
- _heartbeat_loop()：背景心跳任務（每秒寫一次統一心跳 process_status/*.json）
- _check_port_bindable()：開機前 bind 預檢（失敗即帶原因寫 failed 退出）
- _write_server_status()：寫啟動結果檔（storage_dir/bridge/server_status.json——PHP 讀）

使用方式：
    from .server.server import server_class
    server_class.get_instance().initialize(config)
    server_class.get_instance().start()
"""

import asyncio
import json
import logging
import os
import socket
import time
from contextlib import asynccontextmanager
from pathlib import Path

import uvicorn
from fastapi import FastAPI

from ..runtime import process_list
from ..runtime import process_status
from ..runtime import sc_logger


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

        self._prepare_logging()
        sc_logger.log(f"server starting (host={server_settings['host']} port={server_settings['port']})")

        # 台帳（append-only JSONL）：server 自身啟動——本程序即 uvicorn 伺服器（非子程序）
        process_list.append_spawn(
            self.config,
            name="bridge",
            program="bridge.main",
            pid=os.getpid(),
            ppid=os.getppid(),
        )

        # 程序狀態（自我心跳）：初始化 ＋ 登記自己（目錄＝storage_dir ＋ folders.process_status）
        process_status_dir = str(Path(self.config["storage_dir"]) / self.config.get("folders", {}).get("process_status", "process_status"))
        process_status.initialize(process_status_dir)
        process_status.register("bridge", os.getpid(), "bridge.main")

        # bind 預檢（bind 一次即放——另一程序霸住嘅話唔等 uvicorn sys.exit：帶原因寫 failed、直接退出）
        bind_error_reason = self._check_port_bindable(
            server_settings["host"],
            server_settings["port"],
        )

        # 判定：port 唔 bind 得 → 寫失敗結果檔、退出（PHP 拉起端讀檔即知失敗＋原因）
        if bind_error_reason != "":
            sc_logger.log(f"server start failed: {bind_error_reason}")
            self._write_server_status("failed", bind_error_reason)
            return

        app = FastAPI(lifespan=self._lifespan)
        app.state.config = self.config
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

        # 啟動成功（bind 已過、lifespan 就緒）→ 寫 running（PHP 拉起後嘅成功判準）
        self._write_server_status("running")

        yield
        heartbeat_task.cancel()

    async def _heartbeat_loop(self) -> None:
        """背景心跳任務（每秒寫一次程序狀態——統一心跳 process_status/*.json）。"""
        while True:
            # 程序狀態（自我心跳——每輪寫一次：本程序存在＝在跑）
            process_status.beat_all()

            await asyncio.sleep(1)

    def _check_port_bindable(self, host: str, port) -> str:
        """開機前 bind 預檢（bind 到即放；成功回 ""，失敗回原因字串）。"""
        # 解析目標位址（IPv4／IPv6 通吃——同 uvicorn 用同一套 address 語意）
        try:
            address_info = socket.getaddrinfo(host, int(port), type=socket.SOCK_STREAM)[0]
        except socket.gaierror as resolve_error:
            return self._format_start_error(resolve_error)

        address_family = address_info[0]
        address_sockaddr = address_info[4]

        probe_socket = socket.socket(address_family, socket.SOCK_STREAM)

        try:
            probe_socket.bind(address_sockaddr)
        except OSError as bind_error:
            return self._format_start_error(bind_error)
        finally:
            probe_socket.close()

        return ""

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
        from .server_health import build_router as build_health_router
        from .server_daemon import router as daemon_router
        from .server_diagnostics import router as diagnostics_router
        from .server_process import router as process_router
        from .server_projects import router as projects_router
        from .server_stream import build_router as build_stream_router

        app.include_router(build_health_router())
        app.include_router(daemon_router)
        app.include_router(diagnostics_router)
        app.include_router(process_router)
        app.include_router(projects_router)
        app.include_router(build_stream_router())

    def _prepare_logging(self) -> None:
        """將 uvicorn logger 導向 audit.log。"""
        audit_logger = logging.getLogger("runtime.audit")

        for uvicorn_logger_name in ("uvicorn", "uvicorn.error"):
            uvicorn_logger = logging.getLogger(uvicorn_logger_name)
            uvicorn_logger.setLevel(logging.INFO)
            uvicorn_logger.handlers = list(audit_logger.handlers)
            uvicorn_logger.propagate = False
