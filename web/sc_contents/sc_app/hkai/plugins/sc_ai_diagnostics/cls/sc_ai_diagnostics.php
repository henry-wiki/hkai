<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics;

// == sc_ai_diagnostics_Class：插件頂層本體（singleton——bridging sc_ai_diagnostics()） ==
// == 職責：域入口（accessor）——例：sc_ai_diagnostics()->git()->get_git_status_item() ==
// == 2026-09-27 域收攏（舊 2 支 sc_ai_diagnostics__<x>() bridging 收成頂層單一入口） ==

class sc_ai_diagnostics_Class
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
     * files 域（files_Class——long_paths）
     *
     * @return \sc\app\hkai\plugins\sc_ai_diagnostics\files\files_Class
     */
    public function files()
    {
        return files\files_Class::getInstance();
    }

    /**
     * git 域（git_Class——status）
     *
     * @return \sc\app\hkai\plugins\sc_ai_diagnostics\git\git_Class
     */
    public function git()
    {
        return git\git_Class::getInstance();
    }

    /**
     * system_env 域（system_env_Class——系統級環境索要／寫檔／渲染）
     *
     * @return \sc\app\hkai\plugins\sc_ai_diagnostics\system_env\system_env_Class
     */
    public function system_env()
    {
        return system_env\system_env_Class::getInstance();
    }

    /**
     * project_env 域（project_env_Class——專案級環境索要（帶專案路徑））
     *
     * @return \sc\app\hkai\plugins\sc_ai_diagnostics\project_env\project_env_Class
     */
    public function project_env()
    {
        return project_env\project_env_Class::getInstance();
    }
}
