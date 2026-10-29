<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\guard;

// == sc_ai_chats draft get_prompt_required_fields：draft 域守衛（trait——掛入 draft_Class；cls/draft/guard/） ==
// == 職責：送出前必需欄位清單（單一來源——prompt 域三關、草稿儲存檢查都用同一份） ==
// == 上游：validate_prompt_prerequisites／save_meta；下游：無（純資料） ==

trait get_prompt_required_fields
{
    // 必需欄位（key＝meta key；message＝缺失時顯示文案）
    public function get_prompt_required_fields()
    {
        $required_fields = [];

        $content_field = [];
        $content_field["key"]     = "prompt_text";
        $content_field["message"] = "請填寫訊息內容";
        $required_fields[] = $content_field;

        $provider_field = [];
        $provider_field["key"]     = "provider_id";
        $provider_field["message"] = "請選擇供應商";
        $required_fields[] = $provider_field;

        $model_field = [];
        $model_field["key"]     = "model_id";
        $model_field["message"] = "請選擇模型";
        $required_fields[] = $model_field;

        $chat_mode_field = [];
        $chat_mode_field["key"]     = "chat_mode";
        $chat_mode_field["message"] = "請選擇對話模式";
        $required_fields[] = $chat_mode_field;

        $effort_field = [];
        $effort_field["key"]     = "reasoning_effort";
        $effort_field["message"] = "請選擇推理等級";
        $required_fields[] = $effort_field;

        return $required_fields;
    }
}
