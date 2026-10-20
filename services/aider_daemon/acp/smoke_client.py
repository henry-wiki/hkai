#!/usr/bin/env python3
"""
smoke_client.py

aider_daemon.acp 自測 client（開發用——唔屬運行鏈；刪走唔影響 server）。

用法（由 repo 根跑）：

    python services/aider_daemon/acp/smoke_client.py                    # 只做 handshake（initialize＋session/new——免模型、免 key）
    python services/aider_daemon/acp/smoke_client.py --prompt "你好"     # 加跑一輪真提問（要 AIDER_ACP_MODEL＋provider key）

常用環境變數（spawn server 時原樣帶落去）：
    AIDER_ACP_MODEL     例：openrouter/deepseek/deepseek-v4-flash
    AIDER_ACP_ENV_FILE  例：<repo 根>/.env（provider key 由環境檔帶入）

流程：spawn `python -m aider_daemon.acp.main`（cwd＝services/）→ initialize → session/new
→（可選）prompt → 收工。session/update 文字即時印；權限卡＝終端 y/n 手答。
"""

import argparse
import asyncio
import os
import sys
from pathlib import Path

from acp import PROTOCOL_VERSION, spawn_agent_process
from acp.schema import (
    AllowedOutcome,
    ClientCapabilities,
    Implementation,
    RequestPermissionResponse,
    TextContentBlock,
)

# 服務目錄（spawn cwd 用——必須係 services/：child 行 `python -m aider_daemon.acp.main`，
#  而 cwd 一旦係 aider_daemon/，cwd 入面嘅 acp/ 包會騎劫頂層 `acp`（SDK）import → child 即死）
SERVICE_DIR = Path(__file__).resolve().parent
SERVICES_DIR = SERVICE_DIR.parent.parent


class smoke_client_class:
    """極簡 client：印串流文字；權限卡撳終端 y/n；其餘交 SDK 默認。"""

    # 收 session 事件（更新通知）——文字段即印
    async def session_update(self, session_id=None, update=None, **kwargs) -> None:
        text = getattr(getattr(update, "content", None), "text", None)

        # 判定：有文字段 → 即印（串流效果）
        if text:
            sys.stdout.write(text)
            sys.stdout.flush()

    # 收權限請求（server 出卡）→ 終端手答 → 回揀咗邊個 option
    async def request_permission(self, session_id=None, tool_call=None, options=None, **kwargs):
        title = getattr(tool_call, "title", "") if tool_call is not None else ""

        print()
        print("[permission] " + str(title))
        answer = await asyncio.to_thread(input, "allow? [y/N]: ")

        # 判定：答 y → 揀 allow_once
        if answer.strip().lower() == "y":
            return RequestPermissionResponse(outcome=AllowedOutcome(option_id="allow_once", outcome="selected"))

        # 其餘 → 揀 reject_once（用戶揀咗拒絕——唔係取消請求）
        return RequestPermissionResponse(outcome=AllowedOutcome(option_id="reject_once", outcome="selected"))


async def run_smoke(prompt_text, session_cwd) -> None:
    """spawn server → handshake →（可選）提問 → 完。"""
    client = smoke_client_class()

    async with spawn_agent_process(
        client,
        sys.executable,
        "-m",
        "aider_daemon.acp.main",
        cwd=str(SERVICES_DIR),
        env=dict(os.environ),
    ) as (connection, agent_process):
        initialize_response = await connection.initialize(
            protocol_version=PROTOCOL_VERSION,
            client_capabilities=ClientCapabilities(),
            client_info=Implementation(name="acp_smoke", title="aider_daemon.acp smoke client", version="0.1.0"),
        )
        print("initialize ✓ agent:", initialize_response.agent_info)

        session_response = await connection.new_session(cwd=session_cwd)
        session_id = session_response.session_id
        print("session ✓ id:", session_id)

        # 判定：有帶問題 → 跑一輪真提問（模型＋key 要就緒）
        if prompt_text:
            prompt_response = await connection.prompt(
                session_id=session_id,
                prompt=[TextContentBlock(type="text", text=prompt_text)],
            )
            print()
            print("prompt ✓ stop_reason:", prompt_response.stop_reason)

        print("done.")


def main() -> None:
    parser = argparse.ArgumentParser(description="aider_daemon.acp smoke client")
    parser.add_argument("--prompt", default="", help="帶一輪真提問（默認唔帶——只 handshake）")
    parser.add_argument("--cwd", default=os.getcwd(), help="session 工作目錄（默認＝現行目錄）")
    args = parser.parse_args()

    asyncio.run(run_smoke(args.prompt, args.cwd))


if __name__ == "__main__":
    main()
