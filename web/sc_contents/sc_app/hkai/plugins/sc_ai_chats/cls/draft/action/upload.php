<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft upload：單次 multipart 上傳（trait——掛入 draft_Class）
// == 職責：$_FILES → 驗大小 → store_final_image（驗格式／轉碼／寫檔／縮圖／meta）
// == 上游：request/draft/draft_upload.php（前端 XHR FormData）；下游：draft_Class store_final_image

trait upload
{

    // POST draft_upload：multipart（file＋project_slug＋chat_sid）
    public function draft_upload()
    {
        $project_slug = trim((string)($_POST["project_slug"] ?? ""));
        $chat_sid     = trim((string)($_POST["chat_sid"] ?? ""));
        $draft_key    = $this->get_draft_key($chat_sid);

        // 判定：目錄 key 格式唔合 → 400
        if (!$this->is_valid_draft_key($draft_key)) {
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

        // 判定：冇檔案欄 → 400
        if (!isset($_FILES["file"])) {
            return sc_response()->json(["ok" => false, "error" => "missing file"], 400);
        }

        $upload_file = $_FILES["file"];

        // 判定：PHP 上傳錯誤（中斷／超限）→ 400
        if ($upload_file["error"] !== UPLOAD_ERR_OK) {
            return sc_response()->json(["ok" => false, "error" => "upload failed"], 400);
        }

        // 大小上限（KB → bytes；與前端 data-max_upload_size 同一來源）
        $max_upload_bytes = (int)sc_get_config("hkai.draft.max_upload_size") * 1024;

        // 判定：超過上限 → 413
        if ((int)$upload_file["size"] > $max_upload_bytes) {
            return sc_response()->json(["ok" => false, "error" => "file too large"], 413);
        }

        $original_filename = trim((string)($upload_file["name"] ?? ""));
        $binary            = file_get_contents($upload_file["tmp_name"]);

        // 判定：讀唔到／空 → 400
        if ($binary === false || $binary === "") {
            return sc_response()->json(["ok" => false, "error" => "invalid image data"], 400);
        }

        $stored_info = $this->store_final_image($project_slug, $draft_key, $original_filename, $binary);

        // 判定：存檔失敗（格式／大小／轉碼）→ 更新 meta（更新時間）＋400（後端已零寫入）
        if (count($stored_info) < 1) {
            $this->update_meta($project_slug, $draft_key, ["status" => "active"]);

            return sc_response()->json(["ok" => false, "error" => "unsupported or too large image"], 400);
        }

        $stored_info["ok"]           = true;
        $stored_info["draft_key"]    = $draft_key;
        $stored_info["project_slug"] = $project_slug;
        $stored_info["thumb_data_url"] = "";

        return sc_response()->json($stored_info);
    }
}
