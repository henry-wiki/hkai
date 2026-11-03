<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\git;

// == git_Class：git 監視對象（singleton——只做組裝） ==
// == 上游：monitor_Class 清單、monitor::get_snapshot_diff_for_*() ==
// == 下游：fetch_source_entries／get_live_snapshot／write_snapshot／get_snapshot_diff／sync_snapshot_to_view ==

class git_Class
{
    use fetch_source_entries;
    use get_live_snapshot;
    use write_snapshot;
    use get_snapshot_diff;
    use sync_snapshot_to_view;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct()
    {}

    // 當前專案 git 狀態（每次即時問——唔快取）
    public function build_git_entry($project_path)
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

    // 此刻可否初始化
    public function is_ready()
    {
        $project_slug = sc_stream()->resolve_project_slug();

        // 判定：未選專案（空／home）→ 唔初始化
        if (in_array($project_slug, ["", "home"], true) === true) {
            return false;
        }

        return true;
    }

    // 初始化：取 entries → 建 target → 有變才寫檔 → 回 target
    public function init_snapshot()
    {
        // 判定：此 target 此刻唔需要初始化 → 唔做
        if ($this->is_ready() === false) {
            return;
        }

        $live_entries = $this->fetch_source_entries();

        $this->write_snapshot($live_entries);
    }
}
