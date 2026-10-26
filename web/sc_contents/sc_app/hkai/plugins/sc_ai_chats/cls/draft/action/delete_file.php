<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft delete_file：刪草稿附件（trait——掛入 draft_Class） ==
// == 職責：驗檔名 → 刪檔＋縮圖 → meta 移除該項 ==
// == 上游：request/draft/draft_delete_file.php；下游：draft_Class remove_attachment_meta ==

trait delete_file
{

    // POST draft_delete_file：手動刪除一張（前端 × 掣）
    public function draft_delete_file()
    {
        $input_data = sc_request()->json();

        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));
        $filename     = trim((string)($input_data["filename"] ?? ""));
        $draft_key    = $this->get_draft_key($chat_sid);

        // 判定：目錄 key 格式唔合 → 400
        if (!$this->is_valid_draft_key($draft_key)) {
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

        $project_slug    = (string)$project_row["project_slug"];
        $attachments_dir = $this->get_draft_item_dir($project_slug, $draft_key) . "/attachments";
        $file_path       = $attachments_dir . "/" . $filename;
        $thumb_filename  = preg_replace("/\.(png|jpg)$/", ".thumb.jpg", $filename);
        $thumb_path      = $attachments_dir . "/" . $thumb_filename;

        $deleted = false;

        // 判定：主檔存在 → 刪
        if (is_file($file_path)) {
            $deleted = @unlink($file_path);
        }

        // 判定：縮圖存在 → 刪
        if (is_file($thumb_path)) {
            @unlink($thumb_path);
        }

        $this->remove_attachment_meta($project_slug, $draft_key, $filename);

        return sc_response()->json([
            "ok"       => true,
            "deleted"  => ($deleted === true),
            "filename" => $filename,
        ]);
    }
}
