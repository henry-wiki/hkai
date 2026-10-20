<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark;

// == sc_ai_benchmark_Class：插件頂層本體（singleton——bridging sc_ai_benchmark()） ==
// == 職責：域入口（accessor）——例：sc_ai_benchmark()->main()->get_config() ==
// == 2026-09-27 域收攏（舊 8 支 sc_ai_benchmark__<x>() bridging 收成頂層單一入口） ==

class sc_ai_benchmark_Class
{

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
    {
    }

    /**
     * main 域（main_Class——config／model／bench_source）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\main\main_Class
     */
    public function main()
    {
        return main\main_Class::getInstance();
    }

    /**
     * repo 域（repo_Class——repo_select／repo_update；bench_source 檔讀寫）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\repo\repo_Class
     */
    public function repo()
    {
        return repo\repo_Class::getInstance();
    }

    /**
     * request 域（request_Class——bench／action）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\request\request_Class
     */
    public function request()
    {
        return request\request_Class::getInstance();
    }

    /**
     * bench_source 實例（arena——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\arena\bench_source_arena_Class
     */
    public function instance__arena()
    {
        return benchmark\arena\bench_source_arena_Class::getInstance();
    }

    /**
     * bench_source 實例（swe——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\swe\bench_source_swe_Class
     */
    public function instance__swe()
    {
        return benchmark\swe\bench_source_swe_Class::getInstance();
    }

    /**
     * bench_source 實例（aa——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa\bench_source_aa_Class
     */
    public function instance__aa()
    {
        return benchmark\aa\bench_source_aa_Class::getInstance();
    }

    /**
     * bench_source 實例（llm_stats——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\llm_stats\bench_source_llm_stats_Class
     */
    public function instance__llm_stats()
    {
        return benchmark\llm_stats\bench_source_llm_stats_Class::getInstance();
    }
}
