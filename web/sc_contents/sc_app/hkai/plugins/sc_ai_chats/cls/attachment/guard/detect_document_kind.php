<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\guard;

// == sc_ai_chats attachment detect_document_kind：文件類別判定（magic bytes＋文字啟發——唔信副檔名／client MIME）（trait——掛入 attachment_Class） ==
// == 上游：prompt request upload_attachment（非圖片分支） ==
// == 下游：extract_text_from_plain／extract_text_from_pdf／extract_text_from_docx、store_uploaded_document ==

trait detect_document_kind
{
    // 允許嘅文字類副檔名（內容係文字行先——副檔名只作第二關）
    private $allowed_text_extensions = ["html", "htm", "js", "css", "txt", "md", "php"];

    // 判定文件類別（回 ["kind" => text|pdf|docx|""、"extension" => 小寫副檔名]）
    public function detect_document_kind($binary, $original_filename)
    {
        $detected_extension = strtolower((string)pathinfo($original_filename, PATHINFO_EXTENSION));

        // PDF（magic：%PDF）
        if (substr($binary, 0, 4) === "%PDF") {
            return ["kind" => "pdf", "extension" => "pdf"];
        }

        // ZIP 容器（docx 由抽取器第二關驗 word/document.xml）
        if (substr($binary, 0, 4) === "PK\x03\x04") {
            return ["kind" => "docx", "extension" => "docx"];
        }

        // 文字啟發：無 NUL byte ＋合法 UTF-8
        $is_text_content = false;

        // 判定：無 NUL byte → 可能係文字
        if (strpos($binary, "\0") === false) {
            // 判定：合法 UTF-8 → 當文字
            if (mb_check_encoding($binary, "UTF-8") === true) {
                $is_text_content = true;
            }
        }

        $detected_kind = "";

        // 判定：文字內容＋副檔名在白名單 → text
        if ($is_text_content) {
            // 判定：副檔名允許 → 記 text
            if (in_array($detected_extension, $this->allowed_text_extensions)) {
                $detected_kind = "text";
            }
        }

        return ["kind" => $detected_kind, "extension" => $detected_extension];
    }
}
