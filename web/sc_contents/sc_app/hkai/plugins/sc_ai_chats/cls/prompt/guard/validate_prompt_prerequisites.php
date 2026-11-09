<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\guard;

// == sc_ai_chats prompt validate_prompt_prerequisites：prompt 域守衛（trait——掛入 prompt_Class；送出前第一關） ==
// == 2026-09-27 自 message/validate.php 分獨立檔（one file one function） ==

trait validate_prompt_prerequisites
{
    // 工序 2：必填欄位驗證（有附件可無文字）——通過回 context，失敗回 context.error
    public function validate_prompt_prerequisites($context)
    {
        $project_slug = $context["project_slug"];
        $chat_sid     = $context["chat_sid"];
        $content      = trim((string)$context["content"]);

        $errors = [];

        // 附件數（有圖片附件時——內容可以空白）
        $attachment_count = (int)$context["draft_attachment_count"];
        $has_attachment   = $attachment_count > 0;

        // 內容必填（沒打字＋冇附件 → 沒有問題可記錄）
        if ($content === "") {
            // 判定：有附件 → 唔當錯
            if ($has_attachment === false) {
                $errors["content"] = ["message" => hkai()->i18n()->get("prompt.error.no_content"), "code" => "E_VALIDATION_FATAL"];
            }
        }

        // 其餘必填欄位（清單單一來源住 draft 域——只驗唔改）
        $required_fields = sc_ai_chats()->draft()->get_prompt_required_fields();

        // 逐個必填欄位檢查
        foreach ($required_fields as $required_field_item) {
            $required_field_key = (string)$required_field_item["key"];

            // 判定：內容欄有自己嘅判斷（附件可代替）→ 跳過
            if ($required_field_key === "prompt_text") {
                continue;
            }

            $required_field_value = isset($context[$required_field_key]) ? $context[$required_field_key] : null;

            // 判定：未提交／空值 → 記錯
            if ($required_field_value === null || $required_field_value === "") {
                $errors[$required_field_key] = ["message" => $required_field_item["message"], "code" => "E_VALIDATION_FATAL"];
            }
        }

        // 專案必須存在（空 slug／home 段 → 查 Home 專案；查唔到＝錯）
        // Home 語境（空 slug）→ 呼叫端 fallback：用固定 home 查（空 slug 本身查唔到嘢）
        if ($project_slug === "") {
            $project_slug = sc_get_config("hkai.default_project_slug");
        }

        $context["project_slug"] = $project_slug;

        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：專案查唔到 → 記錄錯誤
        if ($project_row === null) {
            $errors["project_slug"] = ["message" => hkai()->i18n()->get("prompt.error.project_missing") . $project_slug, "code" => "E_VALIDATION_FATAL"];
        }

        // 格式判定（32 hex＝合法 sid；空白＝新對話，未配 sid 都合法）
        $is_chat_sid_blank = ($chat_sid === "");
        $is_chat_sid_valid = sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid);

        // 判定：有值但格式唔合 → 記錄錯誤
        if ($is_chat_sid_blank === false && $is_chat_sid_valid === false) {
            $errors["chat_sid"] = ["message" => hkai()->i18n()->get("prompt.error.chat_invalid") . $chat_sid, "code" => "E_VALIDATION_FATAL"];
        }

        // 判定：有錯 → 寫入 context.error（400），工序即刻停
        if (count($errors) > 0) {
            $error_messages = [];

            // 逐條錯誤 → 收 message（合成一句——modal 直接顯示）
            foreach ($errors as $error_item) {
                $error_messages[] = $error_item["message"];
            }

            $context["error"] = [
                "ok"      => false,
                "code"    => "E_VALIDATION_FATAL",
                "title"   => hkai()->i18n()->get("error.E_VALIDATION_FATAL.title"),
                "message" => implode("\n", $error_messages),
                "hint"    => hkai()->i18n()->get("error.E_VALIDATION_FATAL.hint"),
                "errors"  => $errors,
            ];

            $context["error_status"] = 400;
        }

        return $context;
    }

}
