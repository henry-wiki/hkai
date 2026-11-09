<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\guard;

// == sc_ai_chats prompt validate_prompt_processable：prompt 域守衛（trait——掛入 prompt_Class；送出前第三關） ==
// == 2026-09-27 自 message/validate.php 分獨立檔（one file one function） ==

trait validate_prompt_processable
{
    public static function validate_prompt_processable($input_data)
    {
        $provider_id      = isset($input_data["provider_id"]) ? $input_data["provider_id"] : "";
        $model_id         = isset($input_data["model_id"]) ? $input_data["model_id"] : "";
        $reasoning_effort = isset($input_data["reasoning_effort"]) ? $input_data["reasoning_effort"] : null;

        $errors         = [];
        $context_length = 0;

        // 供應商必填 + 必須有效
        if ($provider_id === "") {
            $errors["provider_id"] = ["message" => hkai()->i18n()->get("prompt.error.provider_missing"), "code" => "E_VALIDATION_RECOVERABLE"];
        } else {
            // provider 包（可用清單先驗——唔在清單唔叫；空 rows 走原有錯誤分支）
            $available_providers = sc_ai_providers()->main()->get_available_providers();
            $provider_model_rows = [];
            if (isset($available_providers[$provider_id])) {
                $provider_instance = sc_ai_providers()->repo()->get_provider_instance($provider_id);
                $provider_model_rows = $provider_instance->repo()->get_models();
            }
            if (empty($provider_model_rows)) {
                $errors["provider_id"] = ["message" => hkai()->i18n()->get("prompt.error.provider_invalid") . $provider_id, "code" => "E_VALIDATION_RECOVERABLE"];
            }

            // 模型必填 + 必須屬於該供應商
            if ($model_id === "") {
                $errors["model_id"] = ["message" => hkai()->i18n()->get("prompt.error.model_missing"), "code" => "E_VALIDATION_RECOVERABLE"];
            } elseif (
                !empty($provider_model_rows)
                && !isset($provider_model_rows[$model_id])
            ) {
                $errors["model_id"] = ["message" => hkai()->i18n()->get("prompt.error.model_invalid") . $model_id . hkai()->i18n()->get("prompt.error.provider_suffix") . $provider_id . "）", "code" => "E_VALIDATION_RECOVERABLE"];
            }

            // 上下文長度必備（model store context_length——存在、數字、> 0；缺＝資料唔齊，同無效 model 同級）
            if (
                $model_id !== ""
                && !empty($provider_model_rows)
                && isset($provider_model_rows[$model_id])
            ) {
                $model_item = sc_ai_providers()->repo()->get_model_item($provider_id, $model_id);
                $context_length = isset($model_item["context_length"]) ? $model_item["context_length"] : 0;

                // 判定：非數字或 ≤ 0 → 記錯誤（拒發）
                if (
                    !is_numeric($context_length)
                    || $context_length <= 0
                ) {
                    $errors["model_id"] = ["message" => hkai()->i18n()->get("prompt.error.context_length") . $model_id, "code" => "E_VALIDATION_RECOVERABLE"];
                }
            }

            // 推理等級（model 支援推理時必填；唔支援推理嘅 model 唔需要）
            $is_model_known = false;

            // 判定：model 有喺 provider 清單 → 可查能力
            if (
                $model_id !== ""
                && !empty($provider_model_rows)
                && isset($provider_model_rows[$model_id])
            ) {
                $is_model_known = true;
            }

            $supported_efforts = [];

            // 判定：model 已知 → 查支援等級
            if ($is_model_known === true) {
                $model_item = sc_ai_providers()->repo()->get_model_item($provider_id, $model_id);
                $supported_efforts = isset($model_item["reasoning_supported_efforts"]) ? $model_item["reasoning_supported_efforts"] : [];
            }

            $is_effort_missing = ($reasoning_effort === null || $reasoning_effort === "");
            $has_supported_efforts = (empty($supported_efforts) === false);

            // 判定：支援推理但冇揀等級 → 必填錯誤
            if (
                $has_supported_efforts === true
                && $is_effort_missing === true
            ) {
                $errors["reasoning_effort"] = ["message" => hkai()->i18n()->get("prompt.error.effort_required"), "code" => "E_VALIDATION_RECOVERABLE"];
            }

            // 判定：有揀但唔在支援清單內 → 無效錯誤
            if (
                $has_supported_efforts === true
                && $is_effort_missing === false
                && !in_array($reasoning_effort, $supported_efforts)
            ) {
                $errors["reasoning_effort"] = ["message" => hkai()->i18n()->get("prompt.error.effort_invalid") . $reasoning_effort, "code" => "E_VALIDATION_RECOVERABLE"];
            }
        }

        // 有錯誤 → 回傳
        if (count($errors) > 0) {
            // 逐條錯誤 → 收 message（合成一句——前端 modal 直接顯示真正原因）
            $error_messages = [];
            foreach ($errors as $error_item) {
                $error_messages[] = $error_item["message"];
            }

            return [
                "ok"      => false,
                "code"    => "E_VALIDATION_RECOVERABLE",
                "title"   => hkai()->i18n()->get("error.E_VALIDATION_RECOVERABLE.title"),
                "message" => implode("
", $error_messages),
                "hint"    => hkai()->i18n()->get("error.E_VALIDATION_RECOVERABLE.hint"),
                "errors"  => $errors,
            ];
        }

        return ["ok" => true, "context_length" => $context_length];
    }

}
