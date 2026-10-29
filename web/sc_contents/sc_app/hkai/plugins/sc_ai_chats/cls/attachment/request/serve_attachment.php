<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\request;

// == sc_ai_chats attachment serve_attachment：attachment 域 request（trait——掛入 attachment_Class；cls/attachment/request/） ==
// == 職責：GET 輸出箱內附件原圖／縮圖（<img> 用，inline 唔下載） ==
// == 上游：request/attachment/serve_attachment.php；下游：poll()->get_existing_message_dir（箱路徑唯一來源） ==

trait serve_attachment
{
    // GET serve_attachment?message_id=&filename=&variant=thumb|full
    public function serve_attachment()
    {
        $message_id = (int)sc_request()->query("message_id", 0);
        $filename   = trim((string)sc_request()->query("filename", ""));
        $variant    = trim((string)sc_request()->query("variant", "thumb"));

        // 判定：message_id 唔合法 → 400
        if ($message_id < 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid message_id"], 400);
        }

        // 判定：檔名格式唔合（只准自家命名）→ 400
        if (preg_match("/^[0-9]+-[a-f0-9]{6}\.(png|jpg|jpeg|webp|gif|bmp)$/", $filename) !== 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid filename"], 400);
        }

        $message_dir = sc_ai_chats()->poll()->get_existing_message_dir($message_id);

        // 判定：箱唔存在（已刪）→ 404
        if ($message_dir === "") {
            return sc_response()->json(["ok" => false, "error" => "message dir not found"], 404);
        }

        $attachments_dir = $message_dir . "/attachments";
        $meta_path = $message_dir . "/meta.json";

        // 判定：meta 有記附件實際位置（永久附件庫）→ 用該位置
        if (is_file($meta_path) === true) {
            $meta_data = json_decode((string)@file_get_contents($meta_path), true);

            // 判定：attachments_dir 有值且存在 → 改讀永久庫
            if (is_array($meta_data) === true && (string)($meta_data["attachments_dir"] ?? "") !== "") {
                $meta_attachments_dir = (string)$meta_data["attachments_dir"];

                // 判定：永久庫目錄存在 → 使用
                if (is_dir($meta_attachments_dir) === true) {
                    $attachments_dir = $meta_attachments_dir;
                }
            }
        }

        $file_stem       = pathinfo($filename, PATHINFO_FILENAME);
        $origin_path     = $attachments_dir . "/" . $filename;
        $thumb_path      = $attachments_dir . "/" . $file_stem . ".thumb.jpg";
        $serve_path      = $origin_path;

        // 判定：要縮圖且縮圖存在 → 用縮圖
        if ($variant === "thumb") {
            // 判定：縮圖檔存在 → 改讀縮圖
            if (is_file($thumb_path) === true) {
                $serve_path = $thumb_path;
            }
        }

        // 判定：目標檔唔存在 → 404
        if (is_file($serve_path) === false) {
            return sc_response()->json(["ok" => false, "error" => "attachment file not found"], 404);
        }

        $mime_type  = mime_content_type($serve_path);
        $file_bytes = file_get_contents($serve_path);

        // 判定：辨識唔到 MIME → 通用二進位
        if ($mime_type === false) {
            $mime_type = "application/octet-stream";
        }

        return sc_response()
            ->header("Content-Type", $mime_type)
            ->header("Cache-Control", "private, max-age=86400")
            ->body($file_bytes);
    }
}
