<!-- AUTO-GENERATED：改 .github/docs/README/0.1.0/ 內段檔，勿直接改本檔 -->

# HKAI

> 沒有做不到，只有想不到

本機 AI 開發控制台：PHP 網頁介面 ＋ Python 引擎 daemon ＋ SQLite；源碼與對話唔離開你部機。

<!-- screenshot: 01-projects（專案清單） -->

## Screenshots

![HKAI chat interface](docs/screenshots/01-chat.png)

![Model picker](docs/screenshots/02-models.png)

![Arena benchmark leaderboard](docs/screenshots/03-arena.png)

## Features

- **本機執行**：源碼與對話只留喺你部機，無雲端依賴。
- **改檔前必批**：AI 每次改檔都出權限卡，你批准先動手。
- **引擎可切換**：goose／aider 同一介面，唔綁死單一 agent。
- **每專案獨立**：對話、歷史、工作目錄互相隔離。
- **排行榜內建**：SWE-bench／LMArena／AA／LLM Stats 揀模型有依據。
- **長任務可觀察**：程序檢視＋activity log，隨時中止。

<!-- screenshot: 02-chat-permission（對話＋權限卡） -->

## Requirements

Windows 10/11（x64）· Python 3.13 · 約 3 GB 磁碟空間

<!-- screenshot: 03-settings（設定頁） -->

## Quick start

二選一：

1. **可攜版**：保持整個資料夾結構不變 → 雙擊 `execute/HKAI.exe`
2. **手動**：在站台根執行 `php -S 127.0.0.1:8000 -t . url_rewrite.php`（PHP 8.4.26）

## Scope

單一工作站、單一操作者；以本機專案為單位嘅 AI 協作。

## 引擎（Agent）

| 引擎 | 說明 | 額外需要 |
|---|---|---|
| `goose` | Goose CLI 引擎 | 已附 `externals/goose` |
| `aider` | Aider 引擎 | 已附 `externals/aider`（或用 `.env` 指向自裝位置） |

## 支援的供應商

| 供應商 | 取得 API Key | 備註 |
|---|---|---|
| OpenRouter | <https://openrouter.ai/keys> | 可用免費模型 |
| Kilo | <https://kilo.ai> | 註冊後取得 API Key |
| DeepSeek | <https://platform.deepseek.com/api_keys> | 按用量收費 |

以上三個為已測試；其他供應商未經測試，可自行配置。

## 評分來源（Benchmark）

| 來源 | API Key | 網站 |
|---|---|---|
| SWE-bench | 不需要 | <https://www.swebench.com/> |
| LMArena | 不需要 | <https://lmarena.ai/> |
| Artificial Analysis | 需要 | <https://artificialanalysis.ai/> |
| LLM Stats | 需要 | <https://llm-stats.com/> |

## License

MIT License · Copyright (c) 2026 Henry Yau
