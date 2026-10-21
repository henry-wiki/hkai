<?php

namespace sc\app\hkai\plugins\sc_ai_providers;

// == sc_ai_providers_Class：插件頂層本體（singleton——bridging sc_ai_providers()） ==
// == 職責：域入口（accessor）——例：sc_ai_providers()->main()->get_config() ==
// == 2026-09-27 域收攏（舊 8 支 sc_ai_providers__<x>() bridging 收成頂層單一入口） ==

class sc_ai_providers_Class
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
     * main 域（main_Class——config／model／provider）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\main\main_Class
     */
    public function main()
    {
        return main\main_Class::getInstance();
    }

    /**
     * repo 域（repo_Class——repo_select／repo_update；provider 檔讀寫）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\repo\repo_Class
     */
    public function repo()
    {
        return repo\repo_Class::getInstance();
    }

    /**
     * request 域（request_Class——action／view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\request\request_Class
     */
    public function request()
    {
        return request\request_Class::getInstance();
    }

    /**
     * provider 實例（kilo——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\kilo\provider_kilo_Class
     */
    public function instance__kilo()
    {
        return provider\kilo\provider_kilo_Class::getInstance();
    }

    /**
     * provider 實例（deepseek——具名分派；IDE 直接跳）
     *
     * @return \scpp\hkai\plugins\sc_ai_providers\provider\deepseek\provider_deepseek_Class
     */
    public function instance__deepseek()
    {
        return provider\deepseek\provider_deepseek_Class::getInstance();
    }

    /**
     * provider 實例（openrouter——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\provider_openrouter_Class
     */
    public function instance__openrouter()
    {
        return provider\openrouter\provider_openrouter_Class::getInstance();
    }
}
