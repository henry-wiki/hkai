<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\request;

// == sc_ai_chats attachment request_delete_draft_attachment：刪一張草稿附件（trait——掛入 attachment_Class；cls/attachment/request/） ==
// == 職責：驗 sid／檔名 → 刪主檔＋縮圖＋json → 交 draft 域重建 meta 清單 ==
// == 上游：request/attachment/request_delete_draft_attachment.php；下游：delete_draft_attachment＋draft refresh ==

trait request_delete_draft_attachment
{

    // POST request_delete_draft_attachment：手動刪除一張（前端 × 掣）
    public function request_delete_draft_attachment()
    {
        $input_data = sc_request()->json();

        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $filename     = trim((string)($input_data["filename"] ?? ""));

        // 判定：目錄 key 格式唔合 → 400
        if (!sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid)) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat_sid"], 400);
        }

        // 判定：檔名格式唔合（只准自家命名）→ 400
        if (preg_match("/^[0-9]+-[a-f0-9]{6}\.(png|jpg)$/", $filename) !== 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid filename"], 400);
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
        $deleted      = sc_ai_chats()->attachment()->delete_draft_attachment($project_slug, $chat_sid, $filename);

        sc_ai_chats()->draft()->refresh_draft_meta_for_attachments($project_slug, $chat_sid);

        return sc_response()->json([
            "ok"       => true,
            "deleted"  => ($deleted === true),
            "filename" => $filename,
        ]);
    }
}
