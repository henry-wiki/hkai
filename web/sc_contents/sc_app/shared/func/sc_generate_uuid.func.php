<?php
// == sc_app/shared/func/sc_generate_uuid.func.php：通用 UUID 產生（全 app 通用——對外識別碼／DB 用） ==
// == 用法：sc_generate_uuid() → "480d652d1e494fa5b22786ec7cb7c6d1"（UUID v4 去 dash＝32 小寫 hex） ==
// == 性質：CSPRNG（random_bytes——不可預測）；版本位 4／variant 位 10xx 依 RFC 4122 設定 ==

// 產生 UUID v4（回 32 個小寫 hex——去 dash；需要前綴時呼叫端自加，如 q_／msg_）
function sc_generate_uuid()
{
    $random_bytes = random_bytes(16);

    // 版本 4（第 7 byte 高 4 bit = 0100）
    $random_bytes[6] = chr((ord($random_bytes[6]) & 0x0f) | 0x40);
    // variant 10xx（第 9 byte 高 2 bit = 10）
    $random_bytes[8] = chr((ord($random_bytes[8]) & 0x3f) | 0x80);

    return bin2hex($random_bytes);
}
