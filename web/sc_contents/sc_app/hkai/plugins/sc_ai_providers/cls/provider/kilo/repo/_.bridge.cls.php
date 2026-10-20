<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\repo;

// == sc_ai_providers provider/kilo repo：repo 子域本體（bridge 檔——singleton；包內 provider()->repo()；外部入口 sc_ai_providers()->instance__kilo()->repo()） ==
// == 2026-10-06 新增（provider 讀取——每 provider 獨立一包） ==

class repo_Class
{
    use get_provider_model_data;
    use get_models;
    use build_model_row;
    use get_provider_generated_at;

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
