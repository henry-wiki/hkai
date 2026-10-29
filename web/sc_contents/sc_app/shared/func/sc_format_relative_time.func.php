<?php
// == sc_app/shared/func/sc_format_relative_time.func.php：相對時間顯示（全 app 通用——列表時間標籤） ==
// == 用法：sc_format_relative_time("2026-09-20 01:32:21") → "2d"（數字＋單位縮寫——語言中立、免翻譯） ==
// == 階梯（以經過秒數計，唔用日曆日界）：0s-59s／1m-59m／1h-23h／1d-6d／1w-4w／1mo-11mo／1y＋ ==
// == 單位：s＝秒、m＝分鐘、h＝小時、d＝日、w＝週、mo＝月、y＝年（mo 用嚟同分鐘 m 區分） ==

// 相對時間標籤（純轉換——唔碰 DOM、唔碰狀態）
function sc_format_relative_time($datetime)
{
    $updated_timestamp = strtotime($datetime);
    $elapsed_seconds   = time() - $updated_timestamp;

    // 判定：未來時間（時鐘偏差）→ 當剛剛發生
    if ($elapsed_seconds < 0) {
        $elapsed_seconds = 0;
    }

    // 判定：一分鐘內 → 秒
    if ($elapsed_seconds < 60) {
        return $elapsed_seconds . "s";
    }

    // 判定：一小時內 → 分鐘
    if ($elapsed_seconds < 3600) {
        return (int)floor($elapsed_seconds / 60) . "m";
    }

    // 判定：一日內 → 小時
    if ($elapsed_seconds < 86400) {
        return (int)floor($elapsed_seconds / 3600) . "h";
    }

    // 判定：一週內 → 日
    if ($elapsed_seconds < 604800) {
        return (int)floor($elapsed_seconds / 86400) . "d";
    }

    // 判定：四週內 → 週
    if ($elapsed_seconds < 2592000) {
        return (int)floor($elapsed_seconds / 604800) . "w";
    }

    // 判定：一年內 → 月
    if ($elapsed_seconds < 31536000) {
        return (int)floor($elapsed_seconds / 2592000) . "mo";
    }

    return (int)floor($elapsed_seconds / 31536000) . "y";
}
