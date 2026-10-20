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
        $api_token   = trim($request_data["api_token"] ?? "");

        // 判定：未知 provider → 非欄位錯誤（400）
        $provider_item = $this->find_provider($provider_id);
        if ($provider_item === null) {
            return sc_response()->json(["ok" => false, "error" => "unknown provider"], 400);
        }

        $oauth_keys    = hkai__sc_oauth_keys();
        $env_key       = $provider_item["env_key"];
        $current_value = $oauth_keys->get_key($env_key);

        // 值正規化（剝外層引號——用戶可能連引號一齊貼上）
        $api_token = $oauth_keys->normalize_value($api_token);

        // 判定：提交值＝現值 mask（未改動）→ no-op 直接 ok
        $current_mask = $oauth_keys->make_mask($current_value);
        if ($api_token === $current_mask) {
            return sc_response()->json(["ok" => true]);
        }

        // 欄位驗證（§18——field_key 對應 data-field）
        $errors = [];

        // 判定：含換行 → 欄位錯誤（防寫入多行）
        if (preg_match("/[\r\n]/", $api_token)) {
            $errors["api_token"] = hkai__i18n()->get("settings.provider.token_multiline_error");
        }

        // 判定：有錯 → 400＋errors
        if (!empty($errors)) {
            return sc_response()->json(["ok" => false, "errors" => $errors], 400);
        }

        // 寫入 .env（單鍵更新——原子；$api_token 為空＝以空白值寫入＝清除）
        $oauth_keys->write_key($env_key, $api_token);

        return sc_response()->json(["ok" => true]);
    }
}
