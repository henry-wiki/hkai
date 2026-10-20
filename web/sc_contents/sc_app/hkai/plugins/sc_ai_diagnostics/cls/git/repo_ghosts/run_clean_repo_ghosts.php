<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\git\repo_ghosts;

// == sc_ai_diagnostics git run_clean_repo_ghosts：git 域動作（trait——掛入 git_Class；檔＝動作） ==
// == 發問前預檢——清 git 幽靈（git 名單有、磁碟冇；aider repo-map 免白行＋免警告） ==

trait run_clean_repo_ghosts
{
    // 清理該專案嘅 git 幽靈檔（HTTP bridge——Python 執行；PHP 只負責呼叫與讀 JSON）
    public function run_clean_repo_ghosts($project_path)
    {
        return hkai__python_bridge()->request("/diagnostics/git/clean_repo_ghosts", ["project_path" => $project_path]);
    }
}
