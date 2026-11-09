# 更新日志

## 0.3.0 — 2026-11-06

0.3.0 让运行环境自足、安装目录可以只读：Python 3.13 与 OCR 组件随产品附带；所有需要写入的内容（数据库、设置、供应商密钥、日志、版本标记）集中在同一个数据根；整个界面也已完成多语言（繁体中文／英文）。

### 新增

- 内置 Python 3.13（`execute/python313`），含 pip 与服务依赖（FastAPI、Uvicorn、pydantic、psutil、python-dotenv）。
- 内置 OCR：OpenCV、NumPy、Pillow、RapidOCR——没有系统 Python 也能做图片文字识别。
- 运行期设置写入 `<data>/config/httpd-runtime.conf`，不再写入程序目录。
- 供应商 API key 统一存放 `<data>/config/api_keys.json`——设置页与引擎读写同一份，界面一律以遮罩显示（`**************` 加末 4 位）。
- 全界面多语言：PHP 与 JavaScript 每一句用户可见文字都由语言字典提供；繁体中文／英文两份 key 完全一致。
- 安装向导显示安装位置、版本／BUILD，以及每一步的目标文件。

### 变更

- 数据根判断顺序：`HKAI_DATA_DIR` → 站点 `data/`（已有数据库时）→ `%LOCALAPPDATA%\seacorner\hkai\data`。
- Apache 日志与 PID 文件改放 `<data>/logs/`，不再放在 `execute/bin/apache-*/logs/`。
- Launcher 与运行时检查优先使用内置 Python；`.env` 的 `python_executable` 仍可覆盖。
- LocalAppData 探测增加多重 fallback（环境变量／运行账户），FastCGI 与 launcher 解析到同一路径。
- 随产品附带的 `.env` 只含相对机器路径，程序永不写入；所有可变状态移入数据根（数据库版本标记 `<data>/localdb/DB.VERSION`、设置 `<data>/config/settings.json`、密钥 `<data>/config/api_keys.json`、日志、playground）。
- 所有路径一律经同一个数据根入口解析——只读安装目录不会再悄悄走第二条路、分裂出另一个数据位置。

### 移除

- 不再要求用户手动安装 Python 3.13。
- 运行期不再写入程序目录（支持只读安装）。
- `.env.example` 模板——发布包已附带可用的 `.env`，向导也不再创建或迁移环境文件。
