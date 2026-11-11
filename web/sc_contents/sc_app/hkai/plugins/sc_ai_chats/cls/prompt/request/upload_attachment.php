<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt upload_attachment：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 職責：圖片上傳端點（JSON base64 → 落地 → 回 attachment_id；唔做文字抽取） ==
// == 2026-10-22 建（附件功能——圖片：一次抽取、歷史只用文字） ==

trait upload_attachment
{
    // POST upload_attachment：{project_slug, filename, mime_type, data_base64}
    public function upload_attachment()
    {
        $input_data = sc_request()->json();

        $project_slug = isset($input_data["project_slug"]) ? trim((string)$input_data["project_slug"]) : "";
        $original_filename = isset($input_data["filename"]) ? trim((string)$input_data["filename"]) : "";
        $mime_type    = isset($input_data["mime_type"]) ? trim((string)$input_data["mime_type"]) : "";
        $data_base64  = isset($input_data["data_base64"]) ? (string)$input_data["data_base64"] : "";

        // 判定：缺檔名／類型／內容 → 拒絕
        if ($original_filename === "" || $mime_type === "" || $data_base64 === "") {
            return sc_response()->json(["ok" => false, "error" => hkai()->i18n()->get("prompt.error.attach_incomplete")], 400);
        }

        // ===== 專案列（查唔到一律 fallback Home——同 prompt_send 同一條路）=====
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到 → fallback 固定 Home 專案
        if ($project_row === null) {
            $project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        // 判定：連 Home 都冇 → 資料壞
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        $binary_data = base64_decode($data_base64, true);

        // 判定：base64 解碼失敗 → 拒絕
        if ($binary_data === false) {
            return sc_response()->json(["ok" => false, "error" => hkai()->i18n()->get("prompt.error.attach_format")], 400);
        }

        // 先判圖片（原流程不變）
        $detected_image_mime_type = sc_ai_chats()->attachment()->detect_image_mime_type($binary_data);

        $attachment_row = null;

        // 判定：係圖片 → 走原本落地流程
        if ($detected_image_mime_type !== "") {
            $attachment_row = sc_ai_chats()->attachment()->store_uploaded_image($project_row, $original_filename, $mime_type, $binary_data);
        } else {
            // 非圖片 → 判文件類別（html／js／css／txt／md／php／pdf／docx）
            $document_kind_result = sc_ai_chats()->attachment()->detect_document_kind($binary_data, $original_filename);

            $document_kind = (string)$document_kind_result["kind"];

            $document_text = null;

            // 判定：純文字 → 直接抽文字
            if ($document_kind === "text") {
                $document_text = sc_ai_chats()->attachment()->extract_text_from_plain($binary_data);
            }

            // 判定：PDF → 抽文字層（掃描版回 null）
            if ($document_kind === "pdf") {
                $document_text = sc_ai_chats()->attachment()->extract_text_from_pdf($binary_data);
            }

            // 判定：DOCX → 解壓抽文字
            if ($document_kind === "docx") {
                $document_text = sc_ai_chats()->attachment()->extract_text_from_docx($binary_data);
            }

            // 判定：抽到文字 → 落地
            if ($document_text !== null) {
                $attachment_row = sc_ai_chats()->attachment()->store_uploaded_document($project_row, $original_filename, $document_text);
            }
        }

        // 判定：落地失敗（類型／大小）→ 拒絕
        if ($attachment_row === null) {
            $max_upload_size_kb = (int)sc_get_config("hkai.attachments.max_upload_size");

            return sc_response()->json([
                "ok"    => false,
                "error" => hkai()->i18n()->get("prompt.error.attach_type") . $max_upload_size_kb . " KB",
            ], 400);
        }

        $response_json = [];
        $response_json["ok"]                = true;
        $response_json["attachment_id"]     = (int)$attachment_row["attachment_id"];
        $response_json["original_filename"] = $attachment_row["original_filename"];
        $response_json["attachment_status"] = "ready";

        return sc_response()->json($response_json);
    }
}
