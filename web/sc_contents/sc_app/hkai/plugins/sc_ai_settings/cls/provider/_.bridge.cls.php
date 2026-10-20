<?php

namespace sc\app\hkai\plugins\sc_ai_settings\provider;

// == sc_ai_settings provider：provider 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->provider()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：Provider 分頁（模型 API 來源——一頁列全部 provider；每 provider 一鎖匙列：已設＝唯讀＋重設／按重設＝輸入＋儲存＋取消／未設＝輸入＋儲存；儲存寫 .env） ==

class provider_Class
{
    use view\handle_fetch_providers;
    use action\handle_save_provider_settings;

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
    {}

    // provider 清單（硬編——單一來源：頁面列＋儲存驗證一律由此讀；一 provider＝一節＝一條 token）
    private function get_providers()
    {
        $providers   = [];
        $providers[] = ["id" => "openrouter",      "name" => "OpenRouter",           "env_key" => "OPENROUTER_API_KEY"];
        $providers[] = ["id" => "kilo",            "name" => "Kilo",                 "env_key" => "KILO_API_KEY"];
        $providers[] = ["id" => "deepseek",        "name" => "DeepSeek",             "env_key" => "DEEPSEEK_API_KEY"];
        $providers[] = ["id" => "mimo_token_plan", "name" => "MiMo（Token Plan）",    "env_key" => "MIMO_TOKEN_PLAN_API_KEY"];
        $providers[] = ["id" => "mimo",            "name" => "MiMo（Pay as you go）", "env_key" => "MIMO_API_KEY"];

        return $providers;
    }

    // provider 清單查表（id → 該項；未知 → null）
    private function find_provider($provider_id)
    {
        $providers = $this->get_providers();

        // 逐項查（id 完全相符——單一值明確）
        foreach ($providers as $provider_item) {
            if ($provider_item["id"] === $provider_id) {
                return $provider_item;
            }
        }

        return null;
    }

    // Provider 分頁組裝（一頁列全部 provider——讀 .env 現值出 mask；每列鎖匙三態〔唯讀＋重設／輸入＋儲存＋取消／輸入＋儲存〕）
    private function build_provider_settings_page()
    {
        $oauth_keys = hkai__sc_oauth_keys();
        $providers  = $this->get_providers();

        // === header ===
        $modal_header   = [];
        $modal_header[] = "<div class='modal__title'>Provider</div>";

        // === body ===
        $modal_body   = [];
        $modal_body[] = "<div class='modal__content-main modal-form'>";

            // 逐 provider 生成鎖匙列（三態：saved＝唯讀＋重設；resetting＝輸入＋儲存＋取消；empty＝輸入＋儲存）
            foreach ($providers as $provider_item) {
                $provider_name    = $provider_item["name"];
                $provider_id      = $provider_item["id"];
                $provider_env_key = $provider_item["env_key"];

                $current_value = $oauth_keys->get_key($provider_env_key);
                $mask_value    = $oauth_keys->make_mask($current_value);
                $mask_encoded  = sc_html_encode($mask_value);

                // 列態（已有 key＝saved；未設＝empty；resetting 由前端切態）
                $provider_state = "empty";
                // 判定：已有 key → saved（唯讀＋重設）
                if ($current_value !== "") {
                    $provider_state = "saved";
                }

                // 輸入位提示（saved 態按重設先露——輸入新值；empty 態＝初次輸入）
                $input_placeholder = hkai__i18n()->get("settings.provider.token_placeholder");
                // 判定：已設 → 提示輸入新 Token
                if ($provider_state === "saved") {
                    $input_placeholder = hkai__i18n()->get("settings.provider.token_placeholder_new");
                }

                $modal_body[] = "<div class='modal-form__row form_row provider_key_row' data-provider_state='" . $provider_state . "'>";
                    $modal_body[] = "<div class='modal-form__icon'></div>";
                    $modal_body[] = "<div class='modal-form__info'>";
                        $modal_body[] = "<div class='modal-form__label'>" . $provider_name . "</div>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__content'>";
                        $modal_body[] = "<div class='modal-form__content_double'>";

                            // 唯讀值（saved 態顯示 mask；其餘態 CSS 收起；唔帶 data-field＝唔收錯誤標記）
                            $readonly_attr   = [];
                            $readonly_attr[] = "type='text'";
                            $readonly_attr[] = "class='modal__input'";
                            $readonly_attr[] = "data-provider_part='readonly'";
                            $readonly_attr[] = "value='" . $mask_encoded . "'";
                            $readonly_attr[] = "readonly";
                            $readonly_attr   = implode(" ", $readonly_attr);
                            $modal_body[] = "<input {$readonly_attr}/>";

                            // 輸入位（resetting／empty 態顯示——空值起手）
                            $input_attr   = [];
                            $input_attr[] = "type='text'";
                            $input_attr[] = "class='modal__input'";
                            $input_attr[] = "data-field='api_token'";
                            $input_attr[] = "data-provider_part='input'";
                            $input_attr[] = "value=''";
                            $input_attr[] = "placeholder='" . $input_placeholder . "'";
                            $input_attr   = implode(" ", $input_attr);
                            $modal_body[] = "<input {$input_attr}/>";

                            // 重設（saved 態顯示——入編輯；提交由儲存掣出）
                            $reset_attr   = [];
                            $reset_attr[] = "type='button'";
                            $reset_attr[] = "class='button'";
                            $reset_attr[] = "data-btn='reset_provider_settings'";
                            $reset_attr[] = "data-provider_part='reset'";
                            $reset_attr[] = "data-provider='" . $provider_id . "'";
                            $reset_attr   = implode(" ", $reset_attr);
                            $modal_body[] = "<button {$reset_attr}>" . hkai__i18n()->get("common.reset") . "</button>";

                            // 取消（resetting 態顯示——不進行重設、回唯讀）
                            $cancel_attr   = [];
                            $cancel_attr[] = "type='button'";
                            $cancel_attr[] = "class='button'";
                            $cancel_attr[] = "data-btn='cancel_provider_settings'";
                            $cancel_attr[] = "data-provider_part='cancel'";
                            $cancel_attr[] = "data-provider='" . $provider_id . "'";
                            $cancel_attr   = implode(" ", $cancel_attr);
                            $modal_body[] = "<button {$cancel_attr}>" . hkai__i18n()->get("common.cancel") . "</button>";

                            // 儲存（empty／resetting 態顯示——提交新值）
                            $save_attr   = [];
                            $save_attr[] = "type='button'";
                            $save_attr[] = "class='button button--primary'";
                            $save_attr[] = "data-btn='save_provider_settings'";
                            $save_attr[] = "data-provider_part='save'";
                            $save_attr[] = "data-provider='" . $provider_id . "'";
                            $save_attr   = implode(" ", $save_attr);
                            $modal_body[] = "<button {$save_attr}>" . hkai__i18n()->get("common.save") . "</button>";

                        $modal_body[] = "</div>";
                        $modal_body[] = "<div class='form_err'></div>";
                    $modal_body[] = "</div>";
                $modal_body[] = "</div>";
            }

        $modal_body[] = "</div>";

        $html = [
            "header" => implode("\r\n", $modal_header),
            "footer" => "",
            "body"   => implode("\r\n", $modal_body),
        ];

        return $html;
    }
}
