<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\validate;

// == sc_ai_chats message validate_message_prerequisites：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/validate.php 分獨立檔（one file one function） ==

trait validate_message_prerequisites
{
    public static function validate_message_prerequisites($input_data)
    {
        $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid     = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        $content      = isset($input_data["content"]) ? trim($input_data["content"]) : "";

        $errors = [];

        // 內容必填（沒打字 → 沒有問題可記錄）
        if ($content === "") {
            $errors["content"] = ["message" => "請填寫訊息內容", "code" => "E_VALIDATION_FATAL"];
        }

        // 專案必須存在（空 slug／home 段 → 查 Home 專案；查唔到＝錯）
        // Home 語境（空 slug）→ 呼叫端 fallback：用固定 home 查（空 slug 本身查唔到嘢）
        if ($project_slug === "") {
            $project_slug = "home";
        }

        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：專案查唔到 → 記錄錯誤
        if ($project_row === null) {
            $errors["project_slug"] = ["message" => "專案不存在：" . $project_slug, "code" => "E_VALIDATION_FATAL"];
        }

        // chat_sid 非空 → 對話必須存在
        if ($chat_sid !== "") {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
            if ($chat_row === null) {
                $errors["chat_sid"] = ["message" => "對話不存在：" . $chat_sid, "code" => "E_VALIDATION_FATAL"];
            }
        }

        // 有錯誤 → 回傳（title／hint／message＝後台文案 SSOT——前端零彙整）
        if (count($errors) > 0) {
            $error_messages = [];
            // 逐條錯誤 → 收 message（合成一句——modal 直接顯示）
            foreach ($errors as $error_item) {
                $error_messages[] = $error_item["message"];
            }

            return [
                "ok"      => false,
                "code"    => "E_VALIDATION_FATAL",
                "title"   => hkai__i18n()->get("error.E_VALIDATION_FATAL.title"),
                "message" => implode("\n", $error_messages),
                "hint"    => hkai__i18n()->get("error.E_VALIDATION_FATAL.hint"),
                "errors"  => $errors,
            ];
        }

        return ["ok" => true];
    }

}
