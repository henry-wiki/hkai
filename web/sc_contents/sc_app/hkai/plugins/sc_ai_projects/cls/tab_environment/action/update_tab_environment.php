<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_environment update_tab_environment：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==

trait update_tab_environment
{
    // 更新環境（強制重新偵測＋寫入專案根 ENVIRONMENT.md——環境頁「更新」掣；project_slug 由前端必帶）
    public function update_tab_environment()
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

        // 強制重寫（force＝true——重新偵測）
        $write_result = $this->write_project_environment_file($project_row, true);

        // 判定：偵測／寫入失敗（bridge 未運行等）→ 500（帶原因）
        if ($write_result["ok"] !== true) {
            return sc_response()->json(["ok" => false, "error" => $write_result["error"]], 500);
        }

        return sc_response()->json(["ok" => true, "detected_at" => $write_result["detected_at"] ?? ""]);
    }
}
