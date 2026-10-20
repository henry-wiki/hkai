<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\view;

// == sc_ai_providers provider/openrouter view：view 子域本體（bridge 檔——singleton；包內 provider()->view()；外部入口 sc_ai_providers()->instance__openrouter()->view()） ==
// == 2026-10-06 新增（provider 頁顯示——每 provider 獨立一包） ==

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
