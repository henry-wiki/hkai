<?php

namespace sc\app\hkai\plugins\sc_ai_settings\general\action;

// == sc_ai_settings general handle_settings_save_general：general 域動作（trait——掛入 general_Class；檔＝動作） ==

trait handle_settings_save_general
{
    // POST save general settings handler（驗證後寫入 sc_config/hkai/settings.json——general 組）
    public function handle_settings_save_general()
    {
        $request_data = sc_request()->json();

        $language = trim($request_data["language"] ?? "");

        // 驗證（語言值——errors 阻擋存檔）
        $errors = $this->validate_general_settings($language);

        // 判定：有錯誤 → 不寫檔（標準 errors 結構＋400）
        if (!empty($errors)) {
            return sc_response()->json(["ok" => false, "errors" => $errors], 400);
        }

        // 寫入 sc_config/hkai/settings.json（只動 general 組）
        hkai__core()->write_user_settings("general", ["language" => $language]);

        return sc_response()->json(["ok" => true]);
    }

    // 驗證（語言值——回 errors 表）
    private function validate_general_settings($language)
    {
        $errors = [];

        // 判定：語言為空 → 錯誤
        if ($language === "") {
            $errors["language"] = hkai__i18n()->get("settings.general.error_language_empty");
        }

        // 判定：語言非空但唔支援 → 錯誤（現行語言檔＝cls/i18n/lang）
        if ($language !== "") {
            if (!in_array($language, ["zh-HK", "en-US"], true)) {
                $errors["language"] = hkai__i18n()->get("settings.general.error_language_unsupported") . $language;
            }
        }

        return $errors;
    }
}
