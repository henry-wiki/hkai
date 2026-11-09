# 更新記錄

## 0.3.0 — 2026-11-06

0.3.0 令執行環境變成自足、安裝資料夾可以唯讀：Python 3.13 同 OCR 套件隨產品附上；所有要寫入嘅嘢（資料庫、設定、供應商鎖匙、log、版本標記）全部集中在同一個資料根；整個介面亦已完成多語言（繁中／英文）。

### 新增

- 資料根單一入口：資料庫、設定、供應商 key、日誌、playground、暫存檔全部經同一個解析器（PHP `sc_data_path`、Python `bootstrap.path_resolve`），唯讀安裝唔會再漂移到第二個資料位置。
- `<data>/config/api_keys.json` 為供應商 key 唯一存放處：設定頁寫入、引擎讀取同一個檔。
- `<data>/config/settings.json` 存介面設定（語言、時區），唔再寫入 `.env`。
- 內置 Python 3.13（`execute/python313`）同 OCR 組合（OpenCV、NumPy、Pillow、RapidOCR）——唔需要系統 Python。
- 全介面多語言：PHP 視圖、錯誤訊息、JS 提示全部由語言字典提供；繁中同英文 key 集完全對齊。
- 安裝精靈顯示安裝位置、版本／BUILD 印記，同每一步嘅目標檔案。
- 開頁自檢（preflight）：每次載入檢查可重算值（例如 Home 專案路徑），發現過時即就地修正；每條檢查冪等、例外唔會阻塞頁面。
- 供應商錯誤直接顯示喺回覆：例外歸入 `E_*` 分類（`E_AUTH`、`E_RATE_LIMIT`…），並以人話講要點修（唔寫死任何供應商名）。
- `.deploy` 資料夾：部署清單（`.includes`／`.excludes`／`.mkdirs`）＋`github\\assets` 母本同規格，部署可重現而唔靠人手。

### 變更

- 通道後綴跟現行域名（`.dev.internal` → `-dev`、`.staging.internal` → `-staging`；production 用基底 `data`），PHP 同 Python 計算出同一資料根。
- bridge 啟動確認由等 45 秒縮短至最多 6 秒。
- Home 專案路徑永遠對齊 `<data_dir>/playground`；資料庫舊路徑自動校正。
- origin 白名單改為整份覆寫（每次開頁／啟動 server 以本次來源重寫），唔再累積舊埠。
- 供應商模型更新直接寫入 `<data>/providers/model_store`（讀取仍以 override 層優先）。
- 安裝精靈：移除失效安裝步同兩個過時檢查（mod_rewrite、FcgidMaxRequestLen）；python／goose／env 改為純檢查項。
- SSE 重連：每次開線重置本頁 view 基準，下一 tick 全量補，換線唔會再令 polling 卡住。
- prompt 驗證跟模型能力（推理等級、上下文長度）；偵測專案唔再改寫 URL。
- bridge 工作目錄改去 `<data>/tmp`，執行期源碼目錄零寫入。
- 背景服務共用同一份日誌實作（`bootstrap/sc_logger.py`），統一輪替同存放喺 `<data>/logs`。

### 移除

- 執行期寫入應用程式資料夾（現支援唯讀安裝）。
- 執行期寫入 `.env`，以及 `.env.example`／`.env.package` 模板——發佈只帶一份全相對路徑嘅 `.env`。
- 手動安裝 Python 3.13 嘅要求。
- `SKIP_MODEL_CHECK` 測試旗標——模型存在性永遠檢查。
- 各服務各自一份嘅 logger 同 settings／env 讀取器（現統一一份實作）。
