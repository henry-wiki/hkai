<?php
// == web/sc_prelude/security/sc_uuid7.php：UUIDv7 產生（RFC 9562——時間排序＋唯一）
// == 用法：sc_uuid7() → "0192f7c48a3e7c219f4d2b6e1a0c9d33"
// == 性質：前 48 bit＝Unix 毫秒（字串排序＝時間排序）；版本 7；variant 10xx；其餘 74 bit 隨機
// == 用途：草稿 meta draft_id（時間排序）——同一毫秒靠隨機位避免碰撞

// 產生 UUIDv7（32 hex 精簡形——時間前綴照樣排到序）
function sc_uuid7()
{
    // 現時毫秒（microtime 微秒 → 毫秒整數）
    $now_microtime    = microtime(true);
    $now_milliseconds = (int)floor($now_microtime * 1000);

    // 時間部分（48 bit 大端——pack 64 bit 取後 6 bytes）
    $packed_time    = pack("J", $now_milliseconds);
    $time_bytes     = substr($packed_time, 2, 6);

    // 隨機部分（80 bit——10 bytes）
    $random_bytes   = random_bytes(10);

    // 16 bytes 合體（時間在前、隨機在後）
    $uuid_bytes     = $time_bytes . $random_bytes;

    // 版本位（第 7 byte 高 4 bit 設 0111＝版本 7）
    $uuid_bytes[6]  = chr((ord($uuid_bytes[6]) & 0x0f) | 0x70);

    // variant 位（第 9 byte 高 2 bit 設 10）
    $uuid_bytes[8]  = chr((ord($uuid_bytes[8]) & 0x3f) | 0x80);

    $uuid_hex = bin2hex($uuid_bytes);

    // 32 hex 精簡形（同 chat_sid 一致；時間前綴照樣排到序）
    return $uuid_hex;
}
