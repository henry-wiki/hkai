# 更新日志

## 0.3.0 — 2026-11-06

0.3.0 让运行环境自足、安装目录可以只读：Python 3.13 与 OCR 组件随产品附带；所有需要写入的内容（数据库、设置、供应商密钥、日志、版本标记）集中在同一个数据根；整个界面也已完成多语言（繁体中文／英文）。

### 新增

- 数据根单一入口：数据库、设置、供应商 key、日志、playground、临时文件全部经同一个解析器（PHP `sc_data_path`、Python `bootstrap.path_resolve`），只读安装不会再漂移到第二个数据位置。
- `<data>/config/api_keys.json` 是供应商 key 唯一存放处：设置页写入、引擎读取同一个文件。
- `<data>/config/settings.json` 存放界面设置（语言、时区），不再写入 `.env`。
- 内置 Python 3.13（`execute/python313`）与 OCR 组合（OpenCV、NumPy、Pillow、RapidOCR）——无需系统 Python。
- 全界面多语言：PHP 视图、错误信息、JS 提示全部由语言字典提供；繁中与英文 key 集完全对齐。
- 安装向导显示安装位置、版本／BUILD 标记，以及每一步的目标文件。
- 开页自检（preflight）：每次加载检查可重算的值（例如 Home 项目路径），发现过期即就地修正；每条检查幂等、异常不会阻塞页面。
- 供应商错误直接显示在回复中：异常归入 `E_*` 分类（`E_AUTH`、`E_RATE_LIMIT`…），并以白话说明需要修什么（不写死任何供应商名）。
- `.deploy` 文件夹：部署清单（`.includes`／`.excludes`／`.mkdirs`）＋`github\\assets` 母本与规格，部署可重现而不靠人手。

### 变更

- 通道后缀跟现行域名（`.dev.internal` → `-dev`、`.staging.internal` → `-staging`；production 用基底 `data`），PHP 与 Python 计算出同一数据根。
- bridge 启动确认由等 45 秒缩短至最多 6 秒。
- Home 项目路径永远对齐 `<data_dir>/playground`；数据库旧路径自动校正。
- origin 白名单改为整份覆写（每次开页／启动 server 以本次来源重写），不再累积旧端口。
- 供应商模型更新直接写入 `<data>/providers/model_store`（读取仍以 override 层优先）。
- 安装向导：移除失效安装步与两个过时检查（mod_rewrite、FcgidMaxRequestLen）；python／goose／env 改为纯检查项。
- SSE 重连：每次开线重置本页 view 基准，下一 tick 全量补，换线不会再让 polling 卡住。
- prompt 验证跟模型能力（推理等级、上下文长度）；检测项目不再改写 URL。
- bridge 工作目录改到 `<data>/tmp`，运行期源码目录零写入。
- 后台服务共用同一份日志实现（`bootstrap/sc_logger.py`），统一轮替与存放于 `<data>/logs`。

### 移除

- 运行期写入应用程序文件夹（现支持只读安装）。
- 运行期写入 `.env`，以及 `.env.example`／`.env.package` 模板——发布只带一份全相对路径的 `.env`。
- 手动安装 Python 3.13 的要求。
- `SKIP_MODEL_CHECK` 测试开关——模型存在性始终检查。
- 各服务各自一份的 logger 与 settings／env 读取器（现统一一份实现）。
