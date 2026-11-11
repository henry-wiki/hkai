<?php

namespace sc\app\hkai\plugins\sc_ai_providers\main;

// == sc_ai_providers main：main 域本體（bridge 檔——singleton；呼叫形 sc_ai_providers()->main()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat sc_ai_providers_main 合成） ==
// == 2026-10-06 repo 分層：provider 檔讀寫遷 repo 域（sc_ai_providers()->repo()） ==
// == 2026-10-06 config／provider 系（setup／get_config／initial／get_provider_bundle／get_provider_reset_instance）併回本體 ==
// == 2026-10-06 provider 實例解析入口 get_provider_instance（動態分派＋安全檢查——repo 域分派用） ==

class main_Class
{
    private $config = [];
    private $available_providers = [];
    private $provider_bundle = [];

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
    {
        // bundle 組裝（每 provider 一組：reset_instance——單一來源集中於此）
        $this->provider_bundle = [];
        
        $this->provider_bundle["openrouter"] = [];
        $this->provider_bundle["openrouter"]["reset_instance"] = sc_ai_providers()->instance__openrouter();
        
        $this->provider_bundle["kilo"] = [];
        $this->provider_bundle["kilo"]["reset_instance"] = sc_ai_providers()->instance__kilo();

        $this->provider_bundle["deepseek"] = [];
        $this->provider_bundle["deepseek"]["reset_instance"] = sc_ai_providers()->instance__deepseek();

        $this->provider_bundle["hermes_agent"] = [];
        $this->provider_bundle["hermes_agent"]["reset_instance"] = sc_ai_providers()->instance__hermes_agent();

        $this->provider_bundle["groq"] = [];
        $this->provider_bundle["groq"]["reset_instance"] = sc_ai_providers()->instance__groq();

        $this->provider_bundle["zai"] = [];
        $this->provider_bundle["zai"]["reset_instance"] = sc_ai_providers()->instance__zai();

        $this->provider_bundle["moonshot"] = [];
        $this->provider_bundle["moonshot"]["reset_instance"] = sc_ai_providers()->instance__moonshot();
        $this->provider_bundle["opencode"] = [];
        $this->provider_bundle["opencode"]["reset_instance"] = sc_ai_providers()->instance__opencode();

        $this->provider_bundle["deepinfra"] = [];
        $this->provider_bundle["deepinfra"]["reset_instance"] = sc_ai_providers()->instance__deepinfra();

        $this->provider_bundle["mistral"] = [];
        $this->provider_bundle["mistral"]["reset_instance"] = sc_ai_providers()->instance__mistral();

        $this->provider_bundle["github"] = [];
        $this->provider_bundle["github"]["reset_instance"] = sc_ai_providers()->instance__github();

        $this->provider_bundle["cohere"] = [];
        $this->provider_bundle["cohere"]["reset_instance"] = sc_ai_providers()->instance__cohere();
        $this->provider_bundle["openai"] = [];
        $this->provider_bundle["openai"]["reset_instance"] = sc_ai_providers()->instance__openai();

        $this->provider_bundle["anthropic"] = [];
        $this->provider_bundle["anthropic"]["reset_instance"] = sc_ai_providers()->instance__anthropic();

        $this->provider_bundle["gemini"] = [];
        $this->provider_bundle["gemini"]["reset_instance"] = sc_ai_providers()->instance__gemini();
    }

    public function setup($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    public function get_config($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    // 注入可用 provider 聲明（plugin __init__.php——集中於入口提供；class 只收值）
    public function initial($configs)
    {
        $available_providers = $configs["available_providers"];
        $this->available_providers = $available_providers;

        // 逐個 provider 將對應聲明寫入 provider 例項（main 分派——對面包各自接收）
        foreach ($available_providers as $provider_id => $provider_item) {
            // provider 例項（解析入口——accessor 唔存在時守衛喺 get_provider_instance 內）
            $provider_instance = $this->get_provider_instance($provider_id);

            // 判定：未有實例（尚未實作嘅 provider）→ 跳過（只保留宣告）
            if ($provider_instance === null) {
                continue;
            }

            $provider_instance->initial($provider_item);
        }
    }

    // 可用 provider 聲明（provider_id → 名稱——__init__ 注入；存在＝前台可用）
    public function get_available_providers()
    {
        return $this->available_providers;
    }

    // provider 實例解析入口（provider_id → 例項——動態分派；repo 域分派用）
    public function get_provider_instance($provider_id)
    {
        // 具名分派方法名（instance__{provider_id}——對應 sc_ai_providers_Class 嘅 instance__kilo／instance__openrouter）
        $instance_method_name = "instance__" . $provider_id;

        // 安全檢查：具名分派方法未定義（唔喺 sc_ai_providers_Class）→ 唔呼叫、回 null
        if (!method_exists(sc_ai_providers(), $instance_method_name)) {
            return null;
        }

        // 動態分派（跳轉目標：
        //   1. "kilo"       → sc_ai_providers()->instance__kilo()（cls/provider/kilo/provider_kilo_Class）
        //   2. "openrouter" → sc_ai_providers()->instance__openrouter()（cls/provider/openrouter/provider_openrouter_Class）
        // ）
        return sc_ai_providers()->{$instance_method_name}();
    }
}
