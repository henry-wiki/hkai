<?php

namespace sc\app\hkai\plugins\sc_ai_health\health\registry;

// == sc_ai_health health resolve_health_report：health 域動作（trait——掛入 health_Class；檔＝動作） ==

trait resolve_health_report
{
    // 解析 health 報告（Closure → 當下呼叫並原樣帶入請求資料；其他 → 靜態值原樣回傳）
    private function resolve_health_report($health_report_callback, $request_data)
    {
        // Closure → 每次 health 檢查即時取得（資料會變）；請求資料原樣轉交——報告按需取用
        if ($health_report_callback instanceof \Closure) {
            return $health_report_callback($request_data);
        }

        return $health_report_callback;
    }
}
