<?php

namespace sc\app\hkai\plugins\sc_ai_health\heartbeat\config;

// == sc_ai_health heartbeat initial：heartbeat 域動作（trait——掛入 heartbeat_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat heartbeat_Class 分遷入（one file one action） ==

trait initial
{
    // 注入心跳設定（plugin __init__.php——集中於入口提供；class 只收值）
    public function initial($heartbeatSetup)
    {
        $this->stale_threshold_seconds = intval($heartbeatSetup["stale_threshold_seconds"]);
    }
}
