<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_rule save_tab_rule：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==

trait save_tab_rule
{
    // 儲存規則（寫入專案根 AGENTS.md——project_slug＋content＋opened_update_time 由前端必帶；開啟基準唔對＝409 防覆寫）
    public function save_tab_rule()
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
        $content      = (string)($input_data["content"] ?? "");

        $opened_update_time = trim((string)($input_data["opened_update_time"] ?? ""));

        // 現行檔案更新時間（缺檔＝空字串——同「開啟版本」基準對比）
        $current_update_time = "";
        if (is_file($rule_file)) {
            $current_update_time = (string)@filemtime($rule_file);
        }

        // 判定：開啟版本更新時間 ≠ 現行 → 409（檔案喺開啟後被外部改過——防覆寫；前端彈錯）
        if ($opened_update_time !== $current_update_time) {
            return sc_response()->json(["ok" => false, "error" => hkai__i18n()->get("rule.conflict_error")], 409);
        }

        $write_result = @file_put_contents($rule_file, $content, LOCK_EX);

        // 寫入失敗（權限／目錄已刪等）→ 500
        if ($write_result === false) {
            return sc_response()->json(["ok" => false, "error" => "write rule file failed"], 500);
        }

        return sc_response()->json(["ok" => true]);
    }
}
