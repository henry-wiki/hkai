"""bootstrap — 運行前必經層（結構對應 web/sc_config）。

上游：bridge／diagnostics／goose_daemon／image_extractor／launcher（import 時取資料根）

下游：bootstrap/path.py（路徑名詞：REPO_ROOT／WEB_ROOT／SERVICES_DIR／ENV_FILE）
      bootstrap/path_resolve/（路徑計算：一函數一檔——對應 PHP sc_data_path）
      bootstrap/sc_logger.py（全程序唯一執行日誌——audit／error）
      bootstrap/env.py（.env 入口——對應 PHP sc_env_Class）
      bootstrap/config.py（runtime.json 入口——對應 PHP sc_config_Class）
      bootstrap/load.py（運行前唯一入口——對應 web/sc_config/load.php）
"""

# pyright: reportImportCycles=false
# （理由：__init__ 匯出子模組、子模組相對匯入＝本包刻意橋接寫法，環屬良性）
from . import config, env, load, path, path_resolve
from .path import ENV_FILE, REPO_ROOT, WEB_ROOT, SERVICES_DIR
from .path_resolve.get_data_dir import get_data_dir
from .path_resolve.get_data_sub_dir import get_data_sub_dir
from .path_resolve.resolve_data_dir import resolve_data_dir

__all__ = ["ENV_FILE", "WEB_ROOT", "REPO_ROOT", "SERVICES_DIR", "get_data_dir", "get_data_sub_dir", "resolve_data_dir", "path", "path_resolve", "env", "config", "load"]
