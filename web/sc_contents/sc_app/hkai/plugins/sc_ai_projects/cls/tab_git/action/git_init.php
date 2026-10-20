<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_git git_init：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/handler.php 分遷入（one file one action） ==

trait git_init
{
    public function git_init()
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

        // 準確值：Python 只收 project_path
        $project_path    = $project_row["project_path"];

        hkai__sc_runtime_io()->info("git_init start: project_slug=" . $project_slug);

        $git_init_result = $this->run_python_git_init($project_path);

        // git init 失敗 → 記錄＋400（帶回 Python 的錯誤訊息）
        if ($git_init_result["ok"] === false) {
            hkai__sc_runtime_io()->warn("git_init failed: project_slug=" . $project_slug . " — " . $git_init_result["error"]);

            return sc_response()->json($git_init_result, 400);
        }

        hkai__sc_runtime_io()->info("git_init done: project_slug=" . $project_slug);

        return sc_response()->json($git_init_result);
    }
}
