<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_git git_stage_all：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==

trait git_stage_all
{
    public function git_stage_all()
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
        $project_path     = $project_row["project_path"];

        hkai__sc_runtime_io()->info("git_stage_all start: project_slug=" . $project_slug);

        $git_stage_result = $this->run_python_git_stage_all($project_path);

        // git add -A 失敗 → 記錄＋400（帶回 Python 的錯誤訊息）
        if ($git_stage_result["ok"] === false) {
            hkai__sc_runtime_io()->warn("git_stage_all failed: project_slug=" . $project_slug . " — " . $git_stage_result["error"]);

            return sc_response()->json($git_stage_result, 400);
        }

        hkai__sc_runtime_io()->info("git_stage_all done: project_slug=" . $project_slug);

        return sc_response()->json($git_stage_result);
    }
}
