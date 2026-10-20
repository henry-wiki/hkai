<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\swe\repo;

// == sc_ai_benchmark benchmark/swe repo：repo 子域本體（bridge 檔——singleton；包內 bench_source()->repo()；外部入口 sc_ai_benchmark()->instance__swe()->repo()） ==
// == 2026-10-06 新增（bench_source 讀取——每 bench_source 獨立一包） ==

class repo_Class
{
    use get_bench_source_data;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}
}
