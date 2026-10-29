<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft discard：丟棄草稿（trait——掛入 draft_Class） ==
// == 職責：POST chat_sid → meta.status = discarded（檔案唔刪——交 sweep 清） ==
// == 上游：request/draft/draft_discard.php；下游：draft_Class get_draft_meta／write_meta ==

trait discard
{

    // POST draft_discard：標記丟棄（保留檔案——同一訊息可能仍被引用）
    public function draft_discard()
    {
        $input_data = sc_request()->json();

        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $project_slug = trim((string)($input_data["project_slug"] ?? ""));

        // 判定：目錄 key 格式唔合 → 400
        if (!sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid)) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat_sid"], 400);
        }

        // 專案列（空／無效 slug → fallback Home）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到 → 查 Home
        if ($project_row === null) {
            $project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        // 判定：連 Home 都冇 → 嚴重錯
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        $project_slug = (string)$project_row["project_slug"];
        $meta_data    = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);

        // 判定：冇 meta → 404
        if (count($meta_data) < 1) {
            return sc_response()->json(["ok" => false, "error" => "draft not found"], 404);
        }

        $meta_data["status"]     = "discarded";
        $meta_data["updated_at"] = (int)floor(microtime(true) * 1000);

        $meta_written = sc_ai_chats()->draft()->set_draft_meta($project_slug, $chat_sid, $meta_data);

        // 判定：寫入失敗 → 500
        if ($meta_written === false) {
            return sc_response()->json(["ok" => false, "error" => "cannot write draft meta"], 500);
        }

        return sc_response()->json([
            "ok"        => true,
            "chat_sid"  => $chat_sid,
            "status"    => "discarded",
        ]);
    }
}
