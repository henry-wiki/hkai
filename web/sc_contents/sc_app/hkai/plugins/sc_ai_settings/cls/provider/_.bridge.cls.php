<?php

namespace sc\app\hkai\plugins\sc_ai_settings\provider;

// == sc_ai_settings provider：provider 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->provider()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：Provider 分頁（一頁列全部 provider；每 provider 一鎖匙列：已設＝唯讀＋重設／按重設＝輸入＋儲存＋取消／未設＝輸入＋儲存；儲存寫 .env） ==
// == 清單來源（2026-11-10）：sc_ai_providers 可用 provider 聲明派生（api_key_env）——加 provider 即自動多一列；只有鎖匙嘅額外項列於 extra_providers ==

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

    // provider 清單（唯一來源：sc_ai_providers 可用 provider 聲明——加 provider 即自動多一列）
    private function get_providers()
    {
        $providers = [];

        // 可用 provider 聲明（sc_ai_providers plugin——存在＝前台可用）
        $available_providers = sc_ai_providers()->main()->get_available_providers();

        // 逐個 provider 派生鎖匙列（id／顯示名／鎖匙環境變數名／類別——四項各自落同名變數）
        foreach ($available_providers as $provider_id => $provider_item) {
            // 鎖匙環境變數名（聲明提供；未提供＝此 provider 唔用鎖匙）
            $provider_env_key = isset($provider_item["api_key_env"]) ? $provider_item["api_key_env"] : "";

            // 判定：未提供鎖匙環境變數名 → 跳過（唔列鎖匙列）
            if ($provider_env_key === "") {
                continue;
            }

            $provider_name = $provider_item["provider_name"];

            // 類別（聚合器／直連——聲明提供；缺省＝直連）
            $provider_kind = isset($provider_item["provider_kind"]) ? $provider_item["provider_kind"] : "direct";

            $provider_row           = [];
            $provider_row["id"]      = $provider_id;
            $provider_row["name"]    = $provider_name;
            $provider_row["env_key"] = $provider_env_key;
            $provider_row["kind"]    = $provider_kind;

            $providers[] = $provider_row;
        }

        // 額外鎖匙（只有鎖匙、無模型來源——唔屬 provider 模組，故唔由聲明派生）
        $extra_providers   = [];
        $extra_providers[] = ["id" => "mimo_token_plan", "name" => "MiMo（Token Plan）",    "env_key" => "MIMO_TOKEN_PLAN_API_KEY", "kind" => "direct"];
        $extra_providers[] = ["id" => "mimo",            "name" => "MiMo（Pay as you go）", "env_key" => "MIMO_API_KEY",            "kind" => "direct"];

        // 逐項併入（額外鎖匙排最後——維持原頁面次序）
        foreach ($extra_providers as $extra_provider_item) {
            $providers[] = $extra_provider_item;
        }

        // 分組排序（聚合器行先、直連在後；組內維持原本次序）
        $aggregator_providers = [];
        $direct_providers     = [];

        // 逐列依類別歸組
        foreach ($providers as $provider_item) {
            // 判定：聚合器 → 歸聚合器組
            if ($provider_item["kind"] === "aggregator") {
                $aggregator_providers[] = $provider_item;
                continue;
            }

            $direct_providers[] = $provider_item;
        }

        $providers = array_merge($aggregator_providers, $direct_providers);

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

    // token 顯示遮蔽（空 → ""；短過 4 字 → 全星；否則 18 星＋尾 4 字）
    private function mask_token($token_value)
    {
        // 判定：空值 → 無 mask
        if ($token_value === "") {
            return "";
        }

        // 判定：短過 4 字 → 全星
        if (strlen($token_value) < 4) {
            return str_repeat("*", 18);
        }

        return str_repeat("*", 18) . substr($token_value, -4);
    }

    // token 清洗（API key 唔會含引號／空白／換行——一律移除，最後 trim）
    private function sanitize_token($token_value)
    {
        $removed_chars = ["\"", "'", " ", "\r", "\n"];

        return trim(str_replace($removed_chars, "", $token_value));
    }

    // Provider 分頁組裝（一頁列全部 provider——讀 .env 現值出 mask；每列鎖匙三態〔唯讀＋重設／輸入＋儲存＋取消／輸入＋儲存〕）
    private function build_provider_settings_page()
    {
        $providers = $this->get_providers();

        // === header ===
        $modal_header   = [];
        $modal_header[] = "<div class='modal__title'>" . hkai()->i18n()->get("provider.label") . "</div>";

        // === body ===
        $modal_body   = [];
        $modal_body[] = "<div class='modal__content-main modal-form api_key_list'>";
        $modal_body[] = "<table class='api_key_list_table'>";
        $modal_body[] = "<tbody>";

            // 上一個 provider 類別（分組標題列判定——初值空＝第一列必出標題）
            $last_provider_kind = "";

            // 逐 provider 生成鎖匙列（table 一列一 provider——三態：saved＝唯讀＋重設；resetting＝輸入＋儲存＋取消；empty＝輸入＋儲存）
            foreach ($providers as $provider_item) {
                $provider_name    = $provider_item["name"];
                $provider_id      = $provider_item["id"];
                $provider_env_key = $provider_item["env_key"];
                $provider_kind    = $provider_item["kind"];

                // 組標題列（類別轉變時出一次——聚合器／直連分組）
                if ($provider_kind !== $last_provider_kind) {
                    $group_label        = hkai()->i18n()->get("provider.kind." . $provider_kind);
                    $last_provider_kind = $provider_kind;

                    $modal_body[] = "<tr class='api_key_list_group_row'>";
                    $modal_body[] = "<td colspan='3'>" . $group_label . "</td>";
                    $modal_body[] = "</tr>";
                }

                // 讀 api_keys.json（唯一來源——同 handle_save_provider_settings 寫入同一處）
                $current_value = sc_api_keys()->get_api_key($provider_env_key, "");
                $mask_value    = $this->mask_token($current_value);
                $mask_encoded  = sc_html_encode($mask_value);

                // 列態（已有 key＝saved；未設＝empty；resetting 由前端切態）
                $provider_state = "empty";
                // 判定：已有 key → saved（唯讀＋重設）
                if ($current_value !== "") {
                    $provider_state = "saved";
                }

                // 輸入位提示（saved 態按重設先露——輸入新值；empty 態＝初次輸入）
                $input_placeholder = hkai()->i18n()->get("settings.provider.token_placeholder");
                // 判定：已設 → 提示輸入新 Token
                if ($provider_state === "saved") {
                    $input_placeholder = hkai()->i18n()->get("settings.provider.token_placeholder_new");
                }

                // provider 品牌圖示修飾類（依 provider_id——未有圖嘅 provider 無 background-image＝留空位）
                $icon_class   = "api_key_list_item_icon";
                $icon_class  .= " api_key_list_item_icon--" . $provider_id;

                $modal_body[] = "<tr class='form_row provider_key_row api_key_list_row' data-provider_state='" . $provider_state . "'>";
                    $modal_body[] = "<td class='api_key_list_cell_icon'>";
                        $modal_body[] = "<div class='" . $icon_class . "'></div>";
                    $modal_body[] = "</td>";
                    $modal_body[] = "<td class='api_key_list_cell_name'>";
                        $modal_body[] = "<div class='modal-form__label'>" . $provider_name . "</div>";
                    $modal_body[] = "</td>";
                    $modal_body[] = "<td class='api_key_list_cell_value'>";
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
                            $modal_body[] = "<button {$reset_attr}>" . hkai()->i18n()->get("common.reset") . "</button>";

                            // 取消（resetting 態顯示——不進行重設、回唯讀）
                            $cancel_attr   = [];
                            $cancel_attr[] = "type='button'";
                            $cancel_attr[] = "class='button'";
                            $cancel_attr[] = "data-btn='cancel_provider_settings'";
                            $cancel_attr[] = "data-provider_part='cancel'";
                            $cancel_attr[] = "data-provider='" . $provider_id . "'";
                            $cancel_attr   = implode(" ", $cancel_attr);
                            $modal_body[] = "<button {$cancel_attr}>" . hkai()->i18n()->get("common.cancel") . "</button>";

                            // 儲存（empty／resetting 態顯示——提交新值）
                            $save_attr   = [];
                            $save_attr[] = "type='button'";
                            $save_attr[] = "class='button button--primary'";
                            $save_attr[] = "data-btn='save_provider_settings'";
                            $save_attr[] = "data-provider_part='save'";
                            $save_attr[] = "data-provider='" . $provider_id . "'";
                            $save_attr   = implode(" ", $save_attr);
                            $modal_body[] = "<button {$save_attr}>" . hkai()->i18n()->get("common.save") . "</button>";

                        $modal_body[] = "</div>";
                        $modal_body[] = "<div class='form_err'></div>";
                    $modal_body[] = "</td>";
                $modal_body[] = "</tr>";
            }

        $modal_body[] = "</tbody>";
        $modal_body[] = "</table>";
        $modal_body[] = "</div>";
        $html = [
            "header" => implode("\r\n", $modal_header),
            "footer" => "",
            "body"   => implode("\r\n", $modal_body),
        ];

        return $html;
    }
}
