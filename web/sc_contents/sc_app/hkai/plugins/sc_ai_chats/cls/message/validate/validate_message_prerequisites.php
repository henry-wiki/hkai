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

        // 附件 id（有圖片附件時——內容可以空白）
        $attachment_ids = isset($input_data["attachment_ids"]) ? $input_data["attachment_ids"] : [];
        $has_attachment = is_array($attachment_ids) && count($attachment_ids) > 0;

        // 內容必填（沒打字＋冇附件 → 沒有問題可記錄）
        if ($content === "") {
            // 判定：有附件 → 唔當錯
            if ($has_attachment === false) {
                $errors["content"] = ["message" => "請填寫訊息內容", "code" => "E_VALIDATION_FATAL"];
            }
        }

        // 專案必須存在（空 slug／home 段 → 查 Home 專案；查唔到＝錯）
        // Home 語境（空 slug）→ 呼叫端 fallback：用固定 home 查（空 slug 本身查唔到嘢）
        if ($project_slug === "") {
            $project_slug = sc_get_config("hkai.default_project_slug");
        }

        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：專案查唔到 → 記錄錯誤
        if ($project_row === null) {
            $errors["project_slug"] = ["message" => "專案不存在：" . $project_slug, "code" => "E_VALIDATION_FATAL"];
        }

        // 格式判定（32 hex＝合法 sid；空白＝新對話，未配 sid 都合法）
        $is_chat_sid_blank = ($chat_sid === "");
        $is_chat_sid_valid = sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid);

        // 判定：有值但格式唔合 → 記錄錯誤
        if ($is_chat_sid_blank === false && $is_chat_sid_valid === false) {
            $errors["chat_sid"] = ["message" => "無效的對話 ID：" . $chat_sid, "code" => "E_VALIDATION_FATAL"];
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
                "title"   => hkai()->i18n()->get("error.E_VALIDATION_FATAL.title"),
                "message" => implode("\n", $error_messages),
                "hint"    => hkai()->i18n()->get("error.E_VALIDATION_FATAL.hint"),
                "errors"  => $errors,
            ];
        }

        return ["ok" => true];
    }

}
