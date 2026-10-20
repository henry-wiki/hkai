<?php

namespace sc\app\hkai\plugins\sc_ai_health\health;

// == sc_ai_health health：health 域本體（bridge 檔——singleton；呼叫形 sc_ai_health()->health()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat main_Class 合成） ==

class health_Class
{
    use registry\add_health_action;
    use registry\add_health_report;
    use registry\resolve_health_action;
    use registry\resolve_health_report;
    use request\fetch_health;

    // health 輪詢設定（plugin __init__.php 注入——class 只收值）
    private $health_is_enabled = true;
    private $health_interval_seconds = 9;

    // 外加 health 報告（name → callback；外部模組登記——每次 health 檢查即時取得）
    private $external_health_reports = [];

    // 外加 health 動作（name → callback；外部模組登記——每次 health 檢查即時執行，回傳值丟棄）
    private $external_health_actions = [];

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

    // 注入 health 設定（plugin __init__.php——集中於入口提供；class 只收值）
    public function initial($health_configs)
    {
        $this->health_is_enabled       = (bool)$health_configs["is_enabled"];
        $this->health_interval_seconds = intval($health_configs["interval_seconds"]);
    }

    // 預設健康資料（純資料——checked_at／config／processes；報告桶＝各 domain 經 add_health_report 登記後逐名填入）
    private function get_default_health_data()
    {
        $health_data               = [];
        $health_data["checked_at"] = date("c");

        // 輪詢設定（後台——前端每輪讀取；is_enabled=false → 前端停止輪詢）
        $health_data["config"] = [];
        $health_data["config"]["is_enabled"]       = $this->health_is_enabled;
        $health_data["config"]["interval_seconds"] = $this->health_interval_seconds;

        // -- aider_daemon 存活（已停用——經 bridge 查 pid 每輪成本高；要查改用手動／獨立鏈接）
        $health_data["processes"]    = [];

        // 外加報告桶（外部登記——fetch_health 逐名解析填入）
        $health_data["reports"]    = [];

        return $health_data;
    }
}
