<?php
// == sc_framework/function/security/sc_generate_short_uid.func.php：通用短 UID 產生（框架通用——有序、全小寫） ==
// == 用法：sc_generate_short_uid() → "dyw58tp9vbk"（11 位——6 位 base36 時間戳＋5 位 base36 隨機） ==
// == 性質：時間戳前綴＝有序（同一秒共享前綴）；全小寫 a-z0-9；時間戳自 2000-01-01 00:00:00 UTC 起約 69 年 ==
// == 約定：前綴（如 proj-）由呼叫端自加；長度可傳（6 位時間戳＋（length−6）位隨機；默認 11） ==

// 產生短 UID（時間轉換用原生 base_convert——零迴圈）
function sc_generate_short_uid($length = 11)
{
    $alphabet = "0123456789abcdefghijklmnopqrstuvwxyz";
    $base     = 36;
    
    // 基準日秒數（2000-01-01 00:00:00 UTC 解析一次——request 內快取，零重複）
    static $epoch_seconds = null;
    if ($epoch_seconds === null) {
        $epoch_seconds = strtotime("2000-01-01");
    }
    
    // 時間部分（6 位——原生 base_convert 轉換＋補零，唔自算迴圈）
    $elapsed_seconds = time() - $epoch_seconds;
    $time_part = base_convert((string)$elapsed_seconds, 10, 36);
    $time_part = str_pad($time_part, 6, "0", STR_PAD_LEFT);
    
    // 隨機部分（（length−6）位——同一秒內不重）
    $random_part  = "";
    $random_bytes = random_bytes($length - 6);
    for ($i = 0; $i < $length - 6; $i++) {
        $random_part .= $alphabet[ord($random_bytes[$i]) % $base];
    }
    
    return $time_part . $random_part;
}
