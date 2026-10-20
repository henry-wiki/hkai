<?php

namespace sc\app\hkai\plugins\sc_ai_settings;

// == sc_ai_settings_Class：插件頂層本體（singleton——bridging sc_ai_settings()） ==
// == 職責：域入口（accessor）——例：sc_ai_settings()->settings()->handle_fetch_settings() ==
// == 2026-09-27 域收攏（舊 7 支 sc_ai_settings__<x>() bridging 收成頂層單一入口） ==

class sc_ai_settings_Class
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
     * general 域（general_Class——view；General 分頁＝settings 第一頁）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\general\general_Class
     */
    public function general()
    {
        return general\general_Class::getInstance();
    }

    /**
     * settings 域（settings_Class——config／menu／bench／check）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\settings\settings_Class
     */
    public function settings()
    {
        return settings\settings_Class::getInstance();
    }

    /**
     * about 域（about_Class——view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\about\about_Class
     */
    public function about()
    {
        return about\about_Class::getInstance();
    }

    /**
     * server 域（server_Class——view／action）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\server\server_Class
     */
    public function server()
    {
        return server\server_Class::getInstance();
    }

    /**
     * process 域（process_Class——view／action）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\process\process_Class
     */
    public function process()
    {
        return process\process_Class::getInstance();
    }

    /**
     * instructions 域（instructions_Class——view／action）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\instructions\instructions_Class
     */
    public function instructions()
    {
        return instructions\instructions_Class::getInstance();
    }

    /**
     * provider 域（provider_Class——view；Provider 分頁）
     *
     * @return \sc\app\hkai\plugins\sc_ai_settings\provider\provider_Class
     */
    public function provider()
    {
        return provider\provider_Class::getInstance();
    }
}
