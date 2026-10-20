<?php

namespace sc\app\hkai\plugins\sc_ai_health\heartbeat;

// == sc_ai_health heartbeat：heartbeat 域本體（bridge 檔——singleton；呼叫形 sc_ai_health()->heartbeat()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat heartbeat_Class 合成） ==

class heartbeat_Class
{
    use config\initial;
    use beat\refresh_heartbeat;
    use beat\get_heartbeat_data;

    // 心跳新鮮閾值（秒——initial 注入；心跳檔 mtime/生命年齡超過此值視為過期）
    private $stale_threshold_seconds = null;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}
}
