import sys
import time

from .files import long_paths_class
from .git import config_list_class, git_status_class, repo_ghosts_class
from .project_env import project_env_class
from .runtime import sc_logger
from .system_env import system_env_class

# git 狀態快取秒數（同一專案 60 秒內唔重複掃描——health 每 13 秒一輪）
GIT_STATUS_CACHE_SECONDS = 60

class diagnostics_class:
    def __init__(self):
        self.config_list = config_list_class()
        self.git_status = git_status_class()
        self.long_paths = long_paths_class()
        self.project_env = project_env_class()
        self.repo_ghosts = repo_ghosts_class()
        self.system_env = system_env_class()
        # git 狀態快取（專案路徑 → (取快取時刻, 結果)）——bridge 長駐 process，快取跨請求有效
        self.git_status_cache = {}

    def clean_repo_ghosts(self, project_path):
        return self.repo_ghosts.clean_repo_ghosts(project_path)

    def get_config_list(self, project_path):
        return self.config_list.get_config_list(project_path)

    def get_git_status(self, project_path):
        # 快取未過期 → 即回（唔再全樹掃描——health 每 13 秒一輪嘅自我保護）
        cached_entry = self.git_status_cache.get(project_path)
        if cached_entry is not None:
            cached_at, cached_result = cached_entry
            # 快取仲新鮮 → 直接返回
            if (time.monotonic() - cached_at) < GIT_STATUS_CACHE_SECONDS:
                return cached_result

        git_status_result = self.git_status.get_status(project_path)
        self.git_status_cache[project_path] = (time.monotonic(), git_status_result)
        return git_status_result

    def get_long_paths(self, project_path):
        return self.long_paths.get_long_paths(project_path)

    def get_project_env(self, project_path):
        return self.project_env.get_project_env(project_path)

    def get_system_env(self):
        return self.system_env.get_system_env()

def main():
    sc_logger.setup()
    # Example usage:
    # diagnostics = diagnostics_class()
    # print(diagnostics.get_git_status("some/path"))

if __name__ == "__main__":
    main()
