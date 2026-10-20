<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_rule reset_tab_rule：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==

trait reset_tab_rule
{
    // 重設規則（清除專案根 AGENTS.md——先備份入 storage/hkai/bak；opened_update_time 唔對＝409 防誤刪）
    public function reset_tab_rule()
    {
        $input_data = sc_request()->json();

        // project_slug 前端必帶（缺＝不合法輸入——直接回失敗）
        $project_slug = trim($input_data["project_slug"] ?? "");
        if ($project_slug === "") {
            return sc_response()->json(["ok" => false, "error" => "project_slug is required"], 400);
        }

        $project_row = repo_select()->get_project_row($project_slug);

        // 專案不存在 → 404
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"], 404);
        }

        // 準確值：規則檔＝專案根 AGENTS.md（只收 project_path）
        $project_path = $project_row["project_path"];
        $rule_file    = $project_path . "/AGENTS.md";

        $opened_update_time = trim((string)($input_data["opened_update_time"] ?? ""));

        // 現行檔案更新時間（缺檔＝空字串——同「開啟版本」基準對比）
        $current_update_time = "";
        if (is_file($rule_file)) {
            $current_update_time = (string)@filemtime($rule_file);
        }

        // 判定：開啟版本更新時間 ≠ 現行 → 409（版本唔配對——防誤刪；前端彈錯）
        if ($opened_update_time !== $current_update_time) {
            return sc_response()->json(["ok" => false, "error" => hkai__i18n()->get("rule.conflict_error")], 409);
        }

        // 判定：規則檔存在 → 先備份、後清除（備份＝storage/hkai/bak——保留一日）
        if (is_file($rule_file)) {
            $this->backup_rule_file($project_slug, $rule_file);
            @unlink($rule_file);
        }

        return sc_response()->json(["ok" => true]);
    }

    // 備份規則檔入 storage/hkai/bak（檔名＝{slug}_AGENTS_{Ymd_His}.md；順手清一日前舊份）
    private function backup_rule_file($project_slug, $rule_file)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $storage_dir     = $runtime_configs["storage_dir"];
        $bak_dir         = $storage_dir . "/bak";

        // 判定：bak 目錄不存在 → 遞迴建立
        if (!is_dir($bak_dir)) {
            @mkdir($bak_dir, 0777, true);
        }

        $bak_name = $project_slug . "_AGENTS_" . date("Ymd_His") . ".md";
        @copy($rule_file, $bak_dir . "/" . $bak_name);

        // 清掃：一日前嘅舊備份刪除（保留期＝24 小時）
        $this->sweep_old_rule_backups($bak_dir);
    }

    // 清掃舊備份（mtime 早過一日 → 刪；只掃 *.md——其他怪檔唔動）
    private function sweep_old_rule_backups($bak_dir)
    {
        $bak_files = glob($bak_dir . "/*.md");
        if (!is_array($bak_files)) {
            return;
        }

        $expire_before = time() - 86400;

        foreach ($bak_files as $bak_file_item) {
            $bak_file_mtime = (int)@filemtime($bak_file_item);

            // 判定：早過一日 → 刪
            if ($bak_file_mtime < $expire_before) {
                @unlink($bak_file_item);
            }
        }
    }
}
