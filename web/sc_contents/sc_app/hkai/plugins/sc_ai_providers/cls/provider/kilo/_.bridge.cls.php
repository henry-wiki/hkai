<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo;

// == sc_ai_providers provider/kilo：provider 域本體（bridge 檔——singleton；包內短名 provider()；外部入口 sc_ai_providers()->instance__kilo()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat sc_ai_providers_reset_from_kilo 合成） ==
// == 2026-10-06 自 cls/reset_kilo 遷入 cls/provider/kilo（每 provider 獨立一包） ==
// == 2026-10-06 方法按工作內容分遷（fetch／transform／dirty／build——一檔一動作） ==

class provider_kilo_Class
{
    use fetch\redownload_provider_models;
    use fetch\fetch_models_from_api;
    use transform\reset_model_pricing;
    use transform\reset_model_coding_index;
    use transform\reset_model_available_column;
    use transform\sort_provider_models;
    use dirty\is_dirty_model_item;
    use tier\split_provider_tiers;
    use build\build_provider_file_content;

    // 產檔欄位白名單（reset_model_available_column 保留欄位判定——輸出順序依來源 item 自身次序）
    private $model_available_column = [
        "name",
        "is_free",
        "context_length",
        "pricing_input",
        "pricing_output",
        "pricing_input_cache_read",
        "reasoning_mandatory",
        "reasoning_default_enabled",
        "reasoning_supported_efforts",
        "reasoning_default_effort",
        "intelligence_index",
        "coding_index",
        "agentic_index",
        "created",
        "description",
        
    ];

    private $dirty_model_id_tags = [
        // 用來過濾與審查 Prompt 及輸出內容是否違規（Moderation）
        '~','auto','router',
        
        // 垂直領域：專精金融、醫療或 GUI 介面操控的模型。
        '-fin',
        '-med',
        '-ui-tars',
        
        // 用來過濾與審查 Prompt 及輸出內容是否違規（Moderation）
        'safety:', 'safeguard', 'guard',
        
        // 所有標註 :batch 的模型, 用於非即時、異步的大量數據處理
        ':batch',
        
        // 官方預覽版
        '-1.5b','-1.8b','-3b','-7b','-8b','-tiny',
        
        // 官方預覽版
        'preview-','-preview',
        
        //純搜尋/補丁工具, not for coding
        'perplexity','sonar','image','vision','speech','tts','stt',
        'relace','nano','micro','-search','-vision','-exp',
        
        // 角色扮演：專注於小說創作、對話扮演或無審查模型。
        '-rp','roleplay','story','storytelling','writer','uncensored',
    ];

    // 排序規格（多欄——依序套用；缺省不排）
    private $provider_model_sort_by = [
        // "name asc",
    ];

    // 自身聲明 item（__init__.php 可用 provider——main initial 分派注入）
    private $provider_item = [];

    // initial 完成旗標（main 分派後 true——未完成引用功能即報錯）
    private $initial_done = false;

    // 唔攔白名單（未 initial 都要扑得到——初始化入口 initial；白名單＝__call 即放行）
    private $not_intercepted_methods = [
        "initial",
    ];
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

    // 接收自身聲明 item（main initial 分派——對應本 provider 嗰份；本體只收值）
    private function initial($provider_item)
    {
        $this->provider_item = $provider_item;
        $this->initial_done  = true;
    }

    // 前閘（__call）：外邊扑入一律經此——白名單唔攔；其餘未 initial 即回錯誤、完成放行
    public function __call($method_name, $arguments)
    {
        // 白名單（唔攔——未 initial 都要扑得到；例：初始化入口 initial）
        if (in_array($method_name, $this->not_intercepted_methods)) {
            // 白名單都要存在先轉（打錯名＝無限回捲——唔存在掉落下面常規檢查）
            if (method_exists($this, $method_name)) {
                return $this->$method_name(...$arguments);
            }
        }
        // 未 initial → 即刻回錯誤（唔放行）
        if (!$this->initial_done) {
            return ["ok" => false, "error" => "initial 未完成（kilo）"];
        }
        // 根本冇呢支 → 唔可以轉（防無限回捲）
        if (!method_exists($this, $method_name)) {
            return ["ok" => false, "error" => "unknown method: " . $method_name];
        }
        // 放行（class 內部 scope——扑到 private 真方法）
        return $this->$method_name(...$arguments);
    }

    // 產檔目標路徑（來源＝initial 分派嗰份自身聲明——直接回傳 provider_file）
    private function get_provider_file_path()
    {
        return $this->provider_item["provider_file"];
    }

    // models API URL（來源＝initial 分派嗰份自身聲明——直接回傳 models_api_url）
    public function get_provider_models_api_url()
    {
        return $this->provider_item["models_api_url"];
    }

    /**
     * repo 子域（provider 檔讀取——cls/provider/kilo/repo）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\kilo\repo\repo_Class
     */
    public function repo()
    {
        return repo\repo_Class::getInstance();
    }

    /**
     * view 子域（provider 頁顯示——cls/provider/kilo/view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_providers\provider\kilo\view\view_Class
     */
    public function view()
    {
        return view\view_Class::getInstance();
    }
}
