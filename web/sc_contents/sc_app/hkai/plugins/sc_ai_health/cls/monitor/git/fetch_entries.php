<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\git;

// == git fetch_entries：當前專案 git 狀態（trait——掛入 git_Class） ==
// == 上游：git_Class（經 monitor()->fetch_target_entries()，每個 SSE tick）；下游：DB projects（project_path）＋診斷域 git 狀態 ==
// == 每秒政策：每個 tick 即時問一次 git 狀態（有變化即刻反映——唔做快取／指紋延遲） ==

trait fetch_entries
{
    // 取當刻 entries：全部專案列出（未進過＝空白）；當前專案即時取值
    public function fetch_entries($monitor_context = [])
    {
        $project_slug = (string)($monitor_context["project_slug"] ?? "");

        $project_rows = sc_db()->connection("sqlite")->select(
            "SELECT project_slug, project_path FROM projects WHERE project_status = ?",
            ["active"]
        );

        $git_map = [];

        // ① 全部專案先落空白 template
        foreach ($project_rows as $project_row_item) {
            $git_map[(string)$project_row_item["project_slug"]] = self::build_blank_entry();
        }

        // 判定：冇當前專案 → 全部空白
        if ($project_slug === "") {
            return $git_map;
        }

        // ② 當前專案：即時問 git 狀態
        foreach ($project_rows as $project_row_item) {
            // 判定：正是當前專案 → 取路徑並即時查
            if ((string)$project_row_item["project_slug"] === $project_slug) {
                $project_path = (string)$project_row_item["project_path"];

                $git_map[$project_slug] = self::build_git_entry($project_path);
            }
        }

        return $git_map;
    }

    // 當前專案 git 狀態（每次即時問——唔快取）
    private static function build_git_entry($project_path)
    {
        $git_status = sc_ai_diagnostics()->git()->run_git_status($project_path, true);

        $git_entry = [];

        $git_entry["is_repo"]  = (bool)($git_status["is_repo"] ?? false);
        $git_entry["branch"]   = (string)($git_status["branch"] ?? "");
        $git_entry["detached"] = (bool)($git_status["detached"] ?? false);
        $git_entry["head_sha"] = (string)($git_status["head_sha"] ?? "");
        $git_entry["upstream"] = (string)($git_status["upstream"] ?? "");
        $git_entry["ahead"]    = (int)($git_status["ahead"] ?? 0);
        $git_entry["behind"]   = (int)($git_status["behind"] ?? 0);

        $git_changes = (is_array($git_status["changes"] ?? null) === true) ? $git_status["changes"] : [];

        $git_entry["dirty"] = (int)($git_changes["total"] ?? 0);

        $git_last_commit = (is_array($git_status["last_commit"] ?? null) === true) ? $git_status["last_commit"] : [];

        $git_entry["lastupdate"] = (string)($git_last_commit["committed_at"] ?? "");

        return $git_entry;
    }

    // 空白 entry
    private static function build_blank_entry()
    {
        $git_entry = [];

        $git_entry["is_repo"]    = false;
        $git_entry["branch"]     = "";
        $git_entry["detached"]   = false;
        $git_entry["head_sha"]   = "";
        $git_entry["upstream"]   = "";
        $git_entry["ahead"]      = 0;
        $git_entry["behind"]     = 0;
        $git_entry["dirty"]      = 0;
        $git_entry["lastupdate"] = "";

        return $git_entry;
    }
}
