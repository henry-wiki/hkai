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

    /**
     * provider 實例（hermes_agent——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\hermes_agent\provider_hermes_agent_Class
     */
    public function instance__hermes_agent()
    {
        return provider\hermes_agent\provider_hermes_agent_Class::getInstance();
    }

    /**
     * provider 實例（groq——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\groq\provider_groq_Class
     */
    public function instance__groq()
    {
        return provider\groq\provider_groq_Class::getInstance();
    }

    /**
     * provider 實例（zai——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\zai\provider_zai_Class
     */
    public function instance__zai()
    {
        return provider\zai\provider_zai_Class::getInstance();
    }

    /**
     * provider 實例（moonshot——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\moonshot\provider_moonshot_Class
     */
    public function instance__moonshot()
    {
        return provider\moonshot\provider_moonshot_Class::getInstance();
    }

    /**
     * provider 實例（opencode——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\opencode\provider_opencode_Class
     */
    public function instance__opencode()
    {
        return provider\opencode\provider_opencode_Class::getInstance();
    }

    /**
     * provider 實例（deepinfra——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\deepinfra\provider_deepinfra_Class
     */
    public function instance__deepinfra()
    {
        return provider\deepinfra\provider_deepinfra_Class::getInstance();
    }

    /**
     * provider 實例（mistral——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\mistral\provider_mistral_Class
     */
    public function instance__mistral()
    {
        return provider\mistral\provider_mistral_Class::getInstance();
    }

    /**
     * provider 實例（github——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\github\provider_github_Class
     */
    public function instance__github()
    {
        return provider\github\provider_github_Class::getInstance();
    }

    /**
     * provider 實例（cohere——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\cohere\provider_cohere_Class
     */
    public function instance__cohere()
    {
        return provider\cohere\provider_cohere_Class::getInstance();
    }

    /**
     * provider 實例（openai——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\openai\provider_openai_Class
     */
    public function instance__openai()
    {
        return provider\openai\provider_openai_Class::getInstance();
    }

    /**
     * provider 實例（anthropic——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\anthropic\provider_anthropic_Class
     */
    public function instance__anthropic()
    {
        return provider\anthropic\provider_anthropic_Class::getInstance();
    }

    /**
     * provider 實例（gemini——具名分派；IDE 直接跳）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\gemini\provider_gemini_Class
     */
    public function instance__gemini()
    {
        return provider\gemini\provider_gemini_Class::getInstance();
    }
}
