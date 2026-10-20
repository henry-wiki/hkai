<?php

namespace sc\app\hkai\plugins\sc_ai_providers\repo;

// == sc_ai_providers repo：repo 域本體（bridge 檔——singleton；呼叫形 sc_ai_providers()->repo()） ==
// == 動作（trait——一檔一動作；2026-10-06 自 main 域分遷入） ==

class repo_Class
{
    use repo_select\get_model_item;
    use repo_select\get_provider_instance;
    use repo_update\swap_provider_file_with_single_backup;
    use repo_update\sweep_old_backup_files;
    use repo_update\php_code_export;
    use repo_update\is_list_array;

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
