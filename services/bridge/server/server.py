#!/usr/bin/env python3
"""
server.py — HTTP 伺服器 singleton（FastAPI + uvicorn）。

職責：
- initialize()：載入固定 runtime.json（data/runtime.json）
- start()：啟動 uvicorn（阻塞；bind 預檢＋失敗記錄）
- _lifespan(app)：FastAPI lifespan context（就緒即起心跳；關閉即停）
- _register_routes(app)：掛載所有 router
- _watch_runtime()：背景監控任務（每秒檢查 runtime.json／BUILD——變更即自我退出）
- sc_http.port_available()：開機前 bind 預檢（失敗即記錄原因並退出）

使用方式：
    from .server.server import server_class
    server_class.get_instance().initialize()
    server_class.get_instance().start()
"""

import asyncio
import os
import traceback
from contextlib import asynccontextmanager

import uvicorn
from fastapi import FastAPI

from .. import context
from ..runtime import (
    build_file,
    heartbeat,
    process,
    process_list,
    sc_config,
    sc_logger,
)
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

    def initialize(self) -> None:
        """載入固定 runtime.json（由 main.py 呼叫）。"""
        sc_config.init_config()

    def start(self) -> None:
        """啟動 uvicorn（阻塞直到伺服器停止）。"""
        server_host = str(sc_config.get_config("server_settings", "host"))
        server_port = int(sc_config.get_config("server_settings", "port"))

        sc_logger.log(f"server starting (host={server_host} port={server_port})")

        # 台帳（append-only JSONL）：server 自身啟動——本程序即 uvicorn 伺服器（非子程序）
        process_list.append_spawn(
            pid=process.self_pid(),
            ppid=os.getppid(),
        )

        # bind 預檢（試 bind 一次即放——唔等 uvicorn sys.exit；失敗即記錄並退出）
        port_check = sc_http.port_available(
            server_host,
            server_port,
        )

        # 判定：port 唔可用 → 記錄原因並退出
        if port_check.is_available is False:
            sc_logger.log(f"server start failed: {port_check.message}")
            return

        app = FastAPI(lifespan=self._lifespan)

        self._register_routes(app)

        uvicorn.run(
            app,
            host=server_host,
            port=server_port,
            log_config=None,
            access_log=False,
        )

    def stop(self, reason: str = "") -> None:
        """程序收工：交 heartbeat.stop（清檔）→ lifecycle.exit（即刻終止進程）。"""
        heartbeat.stop(reason)

    @asynccontextmanager
    async def _lifespan(self, app: FastAPI):
        """啟動／關閉掛載點（背景監控任務＋心跳：就緒先開始跳）。"""
        # 背景監控 task（runtime 檔／BUILD）——程式結束時整個 event loop 一齊消失，唔需要 cancel
        asyncio.create_task(self._watch_runtime())

        # 心跳：lifespan 就緒（伺服器真係起到）才開始——起唔到就冇心跳
        heartbeat.init()

        # 啟動成功（bind 已過、lifespan 就緒）→ 心跳已開始（PHP 以心跳判成功）
        yield

        # 正常關閉：收工（self.stop → heartbeat.stop → lifecycle.exit）
        self.stop("server shutdown")

    async def _watch_runtime(self) -> None:
        """背景監控任務（runtime 檔被刪／BUILD 變更 → 自我退出；心跳由 heartbeat 執行緒負責）。"""
        build_file.initialize()

        try:
            while True:
                # 判定：runtime.json 已被刪（建置／清理）→ 自我退出，不留孤兒心跳檔
                if context.RUNTIME_CONFIG_FILE.is_file() is False:
                    self.stop("runtime config removed")

                # 判定：BUILD 已變（站台已重新發佈）→ 記錄後自我退出，等新版本再拉起
                build_watch_result = build_file.changed()
                if build_watch_result["changed"] is True:
                    self.stop("BUILD changed " + build_watch_result["previous"] + " -> " + build_watch_result["current"])

                await asyncio.sleep(1)
        except BaseException:
            # 背景監控例外（唔可以靜靜死——記錄＋收工）
            sc_logger.log("heartbeat loop failed:" + chr(10) + traceback.format_exc())
            self.stop("heartbeat loop failed")

    def _register_routes(self, app: FastAPI) -> None:
        """掛載所有 router。"""
        from .server_daemon import router as daemon_router
        from .server_diagnostics import router as diagnostics_router
        from .server_presence import router as presence_router
        from .server_process import router as process_router
        from .server_projects import router as projects_router
        from .server_stream import build_router as build_stream_router

        app.include_router(daemon_router)
        app.include_router(diagnostics_router)
        app.include_router(presence_router)
        app.include_router(process_router)
        app.include_router(projects_router)
        app.include_router(build_stream_router())
