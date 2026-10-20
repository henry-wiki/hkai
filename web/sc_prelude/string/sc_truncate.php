<?PHP

// 截斷字串（超過上限補省略號；換行／連續空白收斂成單一空白——不改動來源資料）
function sc_truncate($content, $max_chars)
{
    $content   = preg_replace("/\s+/u", " ", trim($content));
    $max_chars = (int)$max_chars;

    // 判定：未超過上限 → 原樣回傳
    if (mb_strlen($content) <= $max_chars) {
        return $content;
    }

    return mb_substr($content, 0, $max_chars) . "…";
}
