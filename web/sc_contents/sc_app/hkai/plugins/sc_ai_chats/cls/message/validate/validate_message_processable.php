<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\validate;

// == sc_ai_chats message validate_message_processable：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/validate.php 分獨立檔（one file one function） ==

trait validate_message_processable
{
    public static function validate_message_processable($input_data)
    {
        $provider_id      = isset($input_data["provider_id"]) ? $input_data["provider_id"] : "";
        $model_id         = isset($input_data["model_id"]) ? $input_data["model_id"] : "";
        $reasoning_effort = isset($input_data["reasoning_effort"]) ? $input_data["reasoning_effort"] : null;

        $errors = [];

        // 供應商必填 + 必須有效
        if ($provider_id === "") {
            $errors["provider_id"] = ["message" => "請選擇模型供應商（provider）", "code" => "E_VALIDATION_RECOVERABLE"];
        } else {
            // provider 包（可用清單先驗——唔在清單唔叫；空 rows 走原有錯誤分支）
            $available_providers = sc_ai_providers()->main()->get_available_providers();
            $provider_model_rows = [];
            if (isset($available_providers[$provider_id])) {
                $provider_instance = sc_ai_providers()->repo()->get_provider_instance($provider_id);
                $provider_model_rows = $provider_instance->repo()->get_models();
            }
            if (empty($provider_model_rows)) {
                $errors["provider_id"] = ["message" => "無效的模型供應商：" . $provider_id, "code" => "E_VALIDATION_RECOVERABLE"];
            }

            // 模型必填 + 必須屬於該供應商
            if ($model_id === "") {
                $errors["model_id"] = ["message" => "請選擇模型（model）", "code" => "E_VALIDATION_RECOVERABLE"];
            } elseif (
                !empty($provider_model_rows)
                && !isset($provider_model_rows[$model_id])
            ) {
                $errors["model_id"] = ["message" => "無效的模型：" . $model_id . "（供應商：" . $provider_id . "）", "code" => "E_VALIDATION_RECOVERABLE"];
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
                    $errors["model_id"] = ["message" => "模型缺少有效上下文長度（context_length）：" . $model_id, "code" => "E_VALIDATION_RECOVERABLE"];
                }
            }

            // 推理等級若提供，必須在 model 支援清單內
            if (
                $reasoning_effort !== null
                && $model_id !== ""
                && !empty($provider_model_rows)
                && isset($provider_model_rows[$model_id])
            ) {
                $model_item = sc_ai_providers()->repo()->get_model_item($provider_id, $model_id);
                $supported_efforts = isset($model_item["reasoning_supported_efforts"]) ? $model_item["reasoning_supported_efforts"] : [];
                if (
                    !empty($supported_efforts)
                    && !in_array($reasoning_effort, $supported_efforts)
                ) {
                    $errors["reasoning_effort"] = ["message" => "無效的推理等級：" . $reasoning_effort, "code" => "E_VALIDATION_RECOVERABLE"];
                }
            }
        }

        // 有錯誤 → 回傳
        if (count($errors) > 0) {
            return [
                "ok"     => false,
                "code"   => "E_VALIDATION_RECOVERABLE",
                "title"  => hkai__i18n()->get("error.E_VALIDATION_RECOVERABLE.title"),
                "hint"   => hkai__i18n()->get("error.E_VALIDATION_RECOVERABLE.hint"),
                "errors" => $errors,
            ];
        }

        return ["ok" => true];
    }

}
