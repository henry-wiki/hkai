# aider_daemon.acp — aider 嘅 ACP server（獨立小程序）

一個好細嘅程式：將本機 **aider** 包成 **ACP server 側**（`goose acp` 嗰個位——aider 官方冇，所以自家寫）。

v0 淨係做：ACP client 連入 → 開對話 → 提問 → 串流答；**aider 想改嘢之前，出卡等用戶確認**（permission 模組）。

---

## 連接圖（邊個同邊個講嘢）

```
ACP client（Zed／我哋自家 daemon／smoke_client.py）
    │  spawn：python -m aider_daemon.acp.main   （services/ 喺 PYTHONPATH）
    │  stdio＝JSON-RPC（stdin 入／stdout 出）
    ▼
services/aider_daemon.acp（本程式）────────── 完全獨立，唔郁任何現有服務
    ├─ core.py              → in-process 呼叫 aider library（Coder.run_stream）
    ├─ permission.py        → 出 request_permission 卡（反向等 client 答案）
    └─ runtime/             → 唔自帶——共用 aider_daemon/runtime/（audit.log／error.log）
                                │
                                ▼
                          aider（pip 套件；provider key 由 spawn env 帶入）
```

- **同 goose_daemon 零關係**：嗰邊係「client 側拉 goose」（官方通道）；呢邊係「server 側被拉」——aider 冇官方通道，所以 aider_daemon 專用本程式做通道。
- **aider_daemon 專用**：揀 aider 引擎＝`daemon_engine: "aider_daemon"` → 自動行本通道；舊 in-process 直驅路（`litellm_*` 七檔）已移除（2026-10-08）。

## 啟動（由 ACP client spawn）

```
python -m aider_daemon.acp.main        # services/ 喺 PYTHONPATH（cwd 任意）
```

| 環境變數 | 用途 | 默認 |
|---|---|---|
| `AIDER_ACP_MODEL` | aider 模型（例：`openrouter/deepseek/deepseek-v4-flash`） | 空＝prompt 一定失敗 |
| `AIDER_ACP_ENV_FILE` | 開機先載此 env 檔（provider key 用——例：repo 根 `.env`） | 空＝唔載 |
| `AIDER_ACP_EDIT_FORMAT` | edit 格式覆寫 | 空＝模型預設 |
| `AIDER_ACP_AUTO_YES` | permission 模組缺席時：自動批准？ | False＝自動拒絕 |

## 接落 HKAI app（chat → 本程式）

```
HKAI 版面打字 → queue → aider_daemon（引擎路：engine/aider_entry.py）→ spawn 本程式 → 答案串流入訊息檔 → 版面顯示
```

- 掣：`hkai_config.json` → `daemon_engine`＝`"aider_daemon"`（本通道）／`"goose_daemon"`（goose 官方通道）——改完下一條訊息生效
- 環境由 aider_daemon 帶入：`AIDER_ACP_MODEL`（＝msg_conf 模型）／`AIDER_ACP_ENV_FILE`（＝repo `.env`）／`AIDER_ACP_EDIT_FORMAT`（chat_mode 對映 ask／architect／code）／`PYTHONPATH`（＝services/）
- 對話連貫：**每輪由 aider_daemon 從 HKAI DB 讀歷史**、隨 `session/new` meta（`history`）帶入——本程式唔持久化（單一 ledger）
- 權限卡：行返現有 `msg_permission.json` 契約——PHP／前端顯示卡，答案寫 `msg_permission_decision.json`
- 轉引擎：`daemon_engine` 改 `"goose_daemon"`（零代碼改動）

## 模組清單（逐個可獨立移除）

| 模組 | 職責 | 移除方式 |
|---|---|---|
| `core.py` | 核心：initialize／new_session／prompt／cancel＋文字串流 | 唔可拆（冇咗唔係 ACP server） |
| `permission.py` | **等確認**（confirm_ask ↔ request_permission 卡） | **刪檔即完成**——核心自動回落自動模式 |
| `litellm_providers.py` | **自訂 provider 註冊**（litellm 唔原生支持嘅 gateway——如 kilo） | **刪檔即完成**——openrouter 原生照行 |
| `litellm_io.py` | **tool 訊息分流**（aider tool 提示／警告／統計 → msg_log 事件） | **刪檔即完成**——tool 訊息回落原始輸出 |
| `litellm_secure.py` | **安全淨化＋摘要止損**（敏感值遮蔽／過長截斷；推理重複／token 超限即中止） | **刪檔即完成**——防護停用，其餘照跑 |
| runtime（共用） | 日誌——用母體 `aider_daemon/runtime/sc_logger.py` | 唔喺本包——冇得刪 |

未來可加（各一檔、獨立可拆）：`sessions`（續話＋持久化）／`modes`（模式對映）／`tool_events`（工具卡）。

## 自測

```
python services/aider_daemon/acp/smoke_client.py                     # 免模型 handshake
python services/aider_daemon/acp/smoke_client.py --prompt "你好"      # 真提問（要 key）
```

## v0 已知邊界

- session 只喺**程序內存**（冇 load／resume）：對話連貫靠 client 每輪帶入歷史（app 接駁已提供）；獨立用＝每輪新 session
- `cancel` 喺「下一個文字段邊界」生效
- 一個程序一個 cwd（多 cwd session 未支援）
- prompt 只收**文字塊**（圖／資源略過）
- permission 卡 = 逐個確認（aider 嘅 group「全部」語義 v0 未對映）
