<?php

// == sc_ai_providers plugin：provider 重生＋模型選擇資料來源 ==
// == 職責：註冊 plugin（asset css＋3 個 request action）＋可用 provider 聲明（main->initial） ==

$route      = $this->get_route();
$controller = sc_controller($route);

// provider 模型資料根（storage——來源檔＋bak 統一住 providers/model_store，唔住插件目錄）
$provider_model_store_dir = sc_get_config("hkai.providers_model_store_dir");

$plugin = $controller->register_plugin(__DIR__, [
    // plugin settings
    "asset" => [
        __DIR__ . "/asset/sc_ai_providers.css",
    ],
]);
$plugin->setEnabled(true);

// 注入可用 provider 聲明（存在＝前台可用——provider id 為 key；值帶 provider_name＋provider_file（指向 model_store/），將來可加）
sc_ai_providers()->main()->initial([
    "available_providers" => [
        
        "openrouter" => [
            "provider_name"  => "Openrouter",
            "provider_file"  => $provider_model_store_dir . "/provider_openrouter.php",
            "api_key_env"    => "OPENROUTER_API_KEY",
            "provider_kind"  => "aggregator",
            "models_api_url" => "https://openrouter.ai/api/v1/models?sort=coding-high-to-low",
            "dirty_filter"   => 1,
        ],
        "kilo"       => [
            "provider_name"  => "Kilo",
            "provider_file"  => $provider_model_store_dir . "/provider_kilo.php",
            "api_key_env"    => "KILO_API_KEY",
            "provider_kind"  => "aggregator",
            "models_api_url" => "https://api.kilo.ai/api/gateway/models",
            "dirty_filter"   => 1,
        ],
        "deepseek"   => [
            "provider_name"  => "DeepSeek",
            "provider_file"  => $provider_model_store_dir . "/provider_deepseek.php",
            "api_key_env"    => "DEEPSEEK_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.deepseek.com/models",
            "dirty_filter"   => 0,
        ],
        "hermes_agent" => [
            "provider_name"  => "Hermes Agent",
            "provider_file"  => $provider_model_store_dir . "/provider_hermes_agent.php",
            "api_key_env"    => "HERMES_AGENT_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://inference-api.nousresearch.com/v1/models",
            "dirty_filter"   => 1,
        ],
        "groq" => [
            "provider_name"  => "Groq",
            "provider_file"  => $provider_model_store_dir . "/provider_groq.php",
            "api_key_env"    => "GROQ_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.groq.com/openai/v1/models",
            "dirty_filter"   => 0,
        ],
        "zai" => [
            "provider_name"  => "Z.ai",
            "provider_file"  => $provider_model_store_dir . "/provider_zai.php",
            "api_key_env"    => "ZAI_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.z.ai/api/paas/v4/models",
            "dirty_filter"   => 0,
        ],
        "moonshot" => [
            "provider_name"  => "Moonshot",
            "provider_file"  => $provider_model_store_dir . "/provider_moonshot.php",
            "api_key_env"    => "MOONSHOT_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.moonshot.ai/v1/models",
            "dirty_filter"   => 0,
        ],
        "opencode" => [
            "provider_name"  => "OpenCode",
            "provider_file"  => $provider_model_store_dir . "/provider_opencode.php",
            "api_key_env"    => "OPENCODE_API_KEY",
            "provider_kind"  => "aggregator",
            "models_api_url" => "https://opencode.ai/inference/v1/models",
            "dirty_filter"   => 0,
        ],
        "deepinfra" => [
            "provider_name"  => "DeepInfra",
            "provider_file"  => $provider_model_store_dir . "/provider_deepinfra.php",
            "api_key_env"    => "DEEPINFRA_API_KEY",
            "provider_kind"  => "aggregator",
            "models_api_url" => "https://api.deepinfra.com/v1/openai/models",
            "dirty_filter"   => 0,
        ],
        "mistral" => [
            "provider_name"  => "Mistral",
            "provider_file"  => $provider_model_store_dir . "/provider_mistral.php",
            "api_key_env"    => "MISTRAL_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.mistral.ai/v1/models",
            "dirty_filter"   => 0,
        ],
        "github" => [
            "provider_name"  => "GitHub Models",
            "provider_file"  => $provider_model_store_dir . "/provider_github.php",
            "api_key_env"    => "GITHUB_MODELS_API_KEY",
            "provider_kind"  => "aggregator",
            "models_api_url" => "https://models.github.ai/catalog/models",
            "dirty_filter"   => 0,
        ],
        "cohere" => [
            "provider_name"  => "Cohere",
            "provider_file"  => $provider_model_store_dir . "/provider_cohere.php",
            "api_key_env"    => "COHERE_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.cohere.com/v1/models?page_size=1000",
            "dirty_filter"   => 0,
        ],
        "openai" => [
            "provider_name"  => "OpenAI",
            "provider_file"  => $provider_model_store_dir . "/provider_openai.php",
            "api_key_env"    => "OPENAI_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.openai.com/v1/models",
            "dirty_filter"   => 0,
        ],
        "anthropic" => [
            "provider_name"  => "Anthropic",
            "provider_file"  => $provider_model_store_dir . "/provider_anthropic.php",
            "api_key_env"    => "ANTHROPIC_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://api.anthropic.com/v1/models",
            "dirty_filter"   => 0,
        ],
        "gemini" => [
            "provider_name"  => "Gemini",
            "provider_file"  => $provider_model_store_dir . "/provider_gemini.php",
            "api_key_env"    => "GEMINI_API_KEY",
            "provider_kind"  => "direct",
            "models_api_url" => "https://generativelanguage.googleapis.com/v1beta/models",
            "dirty_filter"   => 0,
        ],
    ],
]);

$plugin->add_request_action("provider/redownload_provider_models", __DIR__ . "/request/redownload_provider_models.php");

// 註冊 request action（model selection——modal 資料來源）
$plugin->add_request_action("provider/fetch_providers", __DIR__ . "/request/fetch_providers.php");

// 註冊 request action（單一 provider model 列表——撳左欄導航逐個載入）
$plugin->add_request_action("provider/fetch_models", __DIR__ . "/request/fetch_models.php");

