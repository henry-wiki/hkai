<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa\view;

// == sc_ai_benchmark benchmark/aa view：view 子域本體（bridge 檔——singleton；包內 bench_source()->view()；外部入口 sc_ai_benchmark()->instance__aa()->view()） ==
// == 2026-10-07 自 benchmark/swe 複製（bench_source 頁顯示——每 bench_source 獨立一包） ==

class view_Class
{
    use get_model_list_page;

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
