# 更新記錄

## 0.3.0 — 2026-11-06

0.3.0 令執行環境變成自足、安裝資料夾可以唯讀：Python 3.13 同 OCR 套件隨產品附上；所有要寫入嘅嘢（資料庫、設定、供應商鎖匙、log、版本標記）全部集中在同一個資料根；整個介面亦已完成多語言（繁中／英文）。

### 新增

- 內建 Python 3.13（`execute/python313`），含 pip 同服務依賴（FastAPI、Uvicorn、pydantic、psutil、python-dotenv）。
- 內建 OCR：OpenCV、NumPy、Pillow、RapidOCR——冇系統 Python 都做到圖片文字抽取。
- 執行期設定寫入 `<data>/config/httpd-runtime.conf`，唔再寫入程式資料夾。
- 供應商 API key 統一存 `<data>/config/api_keys.json`——設定頁同引擎讀寫同一份，介面一律以遮罩顯示（`**************` 加尾 4 位）。
- 全介面多語言：PHP 同 JavaScript 每一句用戶可見文字都由語言字典提供；繁中／英文兩份 key 完全對齊。
- 安裝精靈顯示安裝位置、版本／BUILD，以及每一步嘅目標檔案。

### 變更

- 資料根判斷次序：`HKAI_DATA_DIR` → 站台 `data/`（已有資料庫時）→ `%LOCALAPPDATA%\seacorner\hkai\data`。
- Apache log 同 PID 檔改放 `<data>/logs/`，唔再喺 `execute/bin/apache-*/logs/`。
- Launcher 同 runtime 檢查優先使用內建 Python；`.env` 嘅 `python_executable` 仍可覆蓋。
- LocalAppData 偵測加多重 fallback（環境變數／執行帳戶），FastCGI 同 launcher 會解析到同一路徑。
- 隨產品附上嘅 `.env` 只含相對機器路徑，程式永遠唔會寫入；所有可變狀態搬入資料根（資料庫版本標記 `<data>/localdb/DB.VERSION`、設定 `<data>/config/settings.json`、鎖匙 `<data>/config/api_keys.json`、log、playground）。
- 所有路徑一律經同一個資料根入口解析——唯讀安裝資料夾唔會再靜靜雞走第二條路、分裂出另一個資料位置。

### 移除

- 唔再要求用戶手動安裝 Python 3.13。
- 執行期唔再寫入程式資料夾（支援唯讀安裝）。
- `.env.example` 範本——發佈包已附可用 `.env`，精靈亦唔再建立或搬遷環境檔。
