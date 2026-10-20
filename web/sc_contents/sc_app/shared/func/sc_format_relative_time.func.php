<?php
// == sc_app/shared/func/sc_format_relative_time.func.php：相對時間顯示（全 app 通用——列表時間標籤） ==
// == 用法：sc_format_relative_time("2026-09-20 01:32:21") → "4d"（唔同時距出 HH:MM／Nd／Nw／Nm／Ny） ==
// == 階梯：今日 HH:MM；1d-6d；1w-3w（<28 日）；1m-11m（<360 日，30 日為一月）；1y＋（360 日起，12 個月＝一年）；依日曆日界 ==

// 相對時間標籤（純轉換——唔碰 DOM、唔碰狀態；顯示慣例＝壓縮階梯）
function sc_format_relative_time($datetime)
{
    $updated_timestamp = strtotime($datetime);
    $updated_day_start = strtotime(date("Y-m-d", $updated_timestamp));
    $today_day_start   = strtotime(date("Y-m-d"));

    // 相差日數（日曆日界——昨日＝1）
    $day_diff = (int)(($today_day_start - $updated_day_start) / 86400);

    // 判定：今日（同一日曆日）→ 只顯示時間
    if ($day_diff < 1) {
        return date("H:i", $updated_timestamp);
    }

    // 判定：一週內 → 日（1d-6d）
    if ($day_diff < 7) {
        return $day_diff . "d";
    }

    // 判定：四週內 → 週（1w-3w）
    if ($day_diff < 28) {
        return (int)floor($day_diff / 7) . "w";
    }

    // 判定：一年內 → 月（1m-11m）
    if ($day_diff < 360) {
        return (int)floor($day_diff / 30) . "m";
    }

    return (int)floor($day_diff / 360) . "y";
}
