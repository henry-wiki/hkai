#!/usr/bin/env python3
"""
aider_daemon.acp 套件——aider 引擎接駁版（ACP server 側；獨立小程序——整包可移除）。

    main.py               入口（薄）：sc_logger.setup() → asyncio.run(run_agent(agent()))
    context.py            統一橋接層（Service Locator）：config()（環境變數）／agent()（agent 單例）
    core.py               ACP agent 本體（initialize／new_session／prompt／cancel＋文字串流）
    permission.py         🧩 可拆模組：等確認（aider confirm ↔ ACP request_permission）
    litellm_providers.py  🧩 可拆模組：自訂 provider 註冊器（gateway 表——如 kilo）
    litellm_io.py         🧩 可拆模組：tool 訊息分流（aider tool 三兄弟 → msg_log 事件）
    litellm_secure.py     🧩 可拆模組：安全淨化（敏感值遮蔽／過長截斷）＋摘要止損（重複／超限）
    smoke_client.py       開發自測 client（handshake 免模型；--prompt 先至叫模型）
    README.md             連接圖＋啟動方式＋模組清單＋移除方法

runtime 唔另設——共用 aider_daemon/runtime/（sc_logger；audit.log／error.log）。
移除一個模組＝刪嗰個檔（缺席＝對應能力自動關——見 core.py 頂部「可拆模組」）。
"""
