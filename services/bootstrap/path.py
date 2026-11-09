"""path.py — 路徑名詞層（對應 PHP SC_REPO_ROOT／SC_WEB_ROOT／SC_ENV_FILE）。

職責：只放「已知固定路徑」常數——零函數、零邏輯、唔 import 其他 bootstrap 模組（避循環）。
資料根唔喺本檔（值隨 .env／DATA_BASE_DIR 變）——一律 path_resolve.get_data_dir()。

上游：任何套件（from bootstrap import path）、bootstrap/env.py（ENV_FILE）
下游：pathlib（stdlib）
"""

from pathlib import Path

# 站台根（bootstrap/ 上兩層——對應 PHP SC_REPO_ROOT）
REPO_ROOT = Path(__file__).resolve().parent.parent.parent

# web 目錄（站台 docroot——對應 PHP SC_WEB_ROOT）
WEB_ROOT = REPO_ROOT / "web"

# services 目錄（各頂層套件所在）
SERVICES_DIR = REPO_ROOT / "services"

# .env 位置（站台根——對應 PHP SC_ENV_FILE）
ENV_FILE = REPO_ROOT / ".env"

__all__ = ["REPO_ROOT", "WEB_ROOT", "SERVICES_DIR", "ENV_FILE"]
