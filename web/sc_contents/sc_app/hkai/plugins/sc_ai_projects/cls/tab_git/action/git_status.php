<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_git git_status：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/handler.php 分遷入（one file one action） ==

trait git_status
{
    public function git_status()
    {
        $input_data = sc_request()->json();

        $project_slug = trim($input_data["project_slug"] ?? "");

        // 缺 slug → 內容回報失敗（HTTP 仍 200）
        if ($project_slug === "") {
            return sc_response()->json(["ok" => false, "error" => "project_slug is required"]);
        }

        $project_row = repo_select()->get_project_row($project_slug);

        // 專案不存在 → 內容回報失敗（HTTP 仍 200）
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"]);
        }

        // 準確值：診斷只收 project_path
        $project_path = $project_row["project_path"];

        return sc_response()->json(sc_ai_diagnostics()->git()->run_git_status($project_path));
    }
}
