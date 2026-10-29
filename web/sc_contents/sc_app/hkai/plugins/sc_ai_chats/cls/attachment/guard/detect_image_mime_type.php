<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\guard;

// == sc_ai_chats attachment detect_image_mime_type：讀 magic bytes 判真實圖片格式（唔係圖片回空字串）（trait——掛入 attachment_Class） ==
// == 上游：attachment 上傳／匯入路徑；下游：GD 函數 ==

trait detect_image_mime_type
{

    // 真實圖片格式（只讀 magic bytes——微秒級；唔係圖片回空字串；public：draft 上傳共用）
    public function detect_image_mime_type($binary)
    {
        $head_bytes = substr($binary, 0, 12);

        // PNG 簽名（hex2bin——避免轉義字元被寫成真換行）
        $png_signature = hex2bin("89504e470d0a1a0a");

        // 判定：PNG 簽名
        if (str_starts_with($head_bytes, $png_signature)) {
            return "image/png";
        }

        // 判定：JPEG 簽名
        if (str_starts_with($head_bytes, "\xFF\xD8\xFF")) {
            return "image/jpeg";
        }

        // 判定：GIF87a
        if (str_starts_with($head_bytes, "GIF87a")) {
            return "image/gif";
        }

        // 判定：GIF89a
        if (str_starts_with($head_bytes, "GIF89a")) {
            return "image/gif";
        }

        $riff_magic = substr($binary, 0, 4);
        $format_magic = substr($binary, 8, 4);

        // 判定：RIFF 容器
        if ($riff_magic === "RIFF") {
            // 判定：RIFF 內係 WEBP
            if ($format_magic === "WEBP") {
                return "image/webp";
            }
        }

        return "";
    }
}
