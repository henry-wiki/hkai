<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\request;

// == sc_ai_projects project handle_project_restore：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-04 還原專案（project_status＝active——歸檔嘅鏡像；對照 handle_chat_restore 而寫） ==

trait handle_project_restore
{
    public function handle_project_restore()
    {
        // 參數（JSON body——還原目標 project_slug——modal 語境照 delete 同一形）
        $input_data = sc_request()->json();
        $project_slug = trim($input_data["project_slug"] ?? "");

        // 查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值）
        $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

        // 判定：Home 專案（slug 空／home）→ 唔准還原／改
        if ($project_slug === "" || $project_slug === "home") {
            return sc_response()->json(["ok" => false, "error" => "home project cannot be modified"], 403);
        }

        // 專案列（還原需要 project_id）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：專案查唔到 → 回錯誤
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "invalid project"], 400);
        }

        // 專案列還原（projects project_status＝active）
        sc_ai_projects()->project()->restore_project((int)$project_row["project_id"]);

        $response_json = [];
        $response_json["ok"]               = true;
        $response_json["project_restored"] = true;

        return sc_response()->json($response_json);
    }
}
