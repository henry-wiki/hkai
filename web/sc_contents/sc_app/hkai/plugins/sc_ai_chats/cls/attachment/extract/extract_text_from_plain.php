<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\extract;

// == sc_ai_chats attachment extract_text_from_plain：純文字抽取（去 BOM／驗 UTF-8／長度上限）（trait——掛入 attachment_Class） ==
// == 上游：prompt request upload_attachment（text 分支） ==
// == 下游：store_uploaded_document（存 .txt） ==

trait extract_text_from_plain
{
    // 純文字抽取（回字串；唔係合法 UTF-8 → null）
    public function extract_text_from_plain($binary)
    {
        $text_value = (string)$binary;

        // 去 UTF-8 BOM（有先剝）
        if (substr($text_value, 0, 3) === "\xEF\xBB\xBF") {
            $text_value = substr($text_value, 3);
        }

        // 判定：非合法 UTF-8 → 拒收
        if (mb_check_encoding($text_value, "UTF-8") === false) {
            return null;
        }

        $text_limit = (int)sc_get_config("hkai.attachments.max_text_chars", 200000);

        // 判定：超長 → 截斷（呼叫端負責標示已截斷）
        if (mb_strlen($text_value, "UTF-8") > $text_limit) {
            $text_value = mb_substr($text_value, 0, $text_limit, "UTF-8");
        }

        return $text_value;
    }
}
