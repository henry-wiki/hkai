<?php

namespace sc\app\hkai\plugins\sc_ai_settings\provider\action;

// == sc_ai_settings provider handle_save_provider_settings：provider 域動作（trait——掛入 provider_Class；檔＝動作） ==
// == 職責：provider API Token 儲存（驗證後寫 .env 該 provider 嘅 <NAME>_API_KEY；提交值＝現值 mask ＝ no-op；空白＝以空白值寫入＝清除——前端已出確認） ==

trait handle_save_provider_settings
{
    // POST save provider handler（驗證 provider_id → 寫 .env；欄位錯誤照 §18 契約）
    public function handle_save_provider_settings()
    {
        $request_data = sc_request()->json();

        $provider_id = trim($request_data["provider_id"] ?? "");
        $api_token   = (string)($request_data["api_token"] ?? "");

        // 判定：未知 provider → 非欄位錯誤（400）
        $provider_item = $this->find_provider($provider_id);
        if ($provider_item === null) {
            return sc_response()->json(["ok" => false, "error" => "unknown provider"], 400);
        }

        $env_key       = $provider_item["env_key"];
        $current_value = sc_api_keys()->get_api_key($env_key, "");

        // 值清洗（引號／空白／換行一律移除——API key 本身不含呢啲字元）
        $api_token = $this->sanitize_token($api_token);

        // 判定：提交值＝現值 mask（未改動）→ no-op 直接 ok
        $current_mask = $this->mask_token($current_value);
        if ($api_token === $current_mask) {
            return sc_response()->json(["ok" => true]);
        }

        // 欄位驗證（§18——field_key 對應 data-field）
        $errors = [];

        // 判定：非空且短過 13 字 → 長度不合（空白＝清除，唔驗）
        if ($api_token !== "" && strlen($api_token) < 13) {
            $errors["api_token"] = hkai()->i18n()->get("settings.provider.token_too_short");
        }

        // 判定：有錯 → 400＋errors
        if (!empty($errors)) {
            return sc_response()->json(["ok" => false, "errors" => $errors], 400);
        }

        // 寫入 api_keys.json（原子覆蓋——$api_token 為空＝以空值寫入＝清除）
        sc_api_keys()->set_api_key($env_key, $api_token);

        return sc_response()->json(["ok" => true]);
    }
}
