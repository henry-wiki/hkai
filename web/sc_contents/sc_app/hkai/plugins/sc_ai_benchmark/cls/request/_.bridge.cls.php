<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request;

// == sc_ai_benchmark request：request 域本體（bridge 檔——singleton；呼叫形 sc_ai_benchmark()->request()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat sc_ai_benchmark_request 合成） ==

class request_Class
{
    use action\handle_redownload_bench_source_models;
    use action\handle_fetch_bench_source;
    use action\handle_fetch_bench_source_navigation;
    use action\handle_fetch_models;
    use view\build_modal_body_html;
    use view\build_modal_tabs_html;
    use view\build_navigation_html;

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
