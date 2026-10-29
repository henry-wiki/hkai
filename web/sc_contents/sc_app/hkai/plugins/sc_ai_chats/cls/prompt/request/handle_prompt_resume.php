<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt handle_prompt_resume：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 職責：重入通知——頁面重新載入時，掃該對話 polling 箱，未開工嘅補派工（回派工數） ==
// == 上游：request/prompt/prompt_resume.php（route）；下游：queue resume_polling_jobs ==

trait handle_prompt_resume
{
    // POST prompt_resume：chat_sid → 確保等待中嘅回覆有人接手
    public function handle_prompt_resume()
    {
        $input_data = sc_request()->json();
        $chat_sid   = (string)($input_data["chat_sid"] ?? "");

        // 判定：冇 chat_sid → 回錯誤
        if ($chat_sid === "") {
            return sc_response()->json(["ok" => false, "error" => "chat_sid is required"], 400);
        }

        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 判定：對話唔存在（已刪）→ 冇嘢要補
        if ($chat_row === null) {
            return sc_response()->json(["ok" => true, "notified" => 0]);
        }

        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat((int)$chat_row["chat_id"]);

        // 判定：專案 slug 空（專案已刪）→ 回錯誤
        if ($project_slug === "") {
            return sc_response()->json(["ok" => false, "error" => "project not found"], 404);
        }

        $notified_count = sc_ai_chats()->queue()->resume_polling_jobs($project_slug, $chat_sid);

        $response_json = [];
        $response_json["ok"]       = true;
        $response_json["notified"] = $notified_count;

        return sc_response()->json($response_json);
    }
}
