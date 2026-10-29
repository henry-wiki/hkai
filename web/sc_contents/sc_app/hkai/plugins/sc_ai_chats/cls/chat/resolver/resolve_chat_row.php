<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat resolve_chat_row：chat 域解析（trait——掛入 chat_Class；cls/chat/resolver/） ==
// == 職責：由 prompt context 確保對話列存在（有＝取回；冇＝由六欄建新列） ==
// == 上游：prompt_send 工序 5；下游：chat repo insert_db_chat／context 六個欄位 ==

trait resolve_chat_row
{
    // 回傳格式：["ok" => bool, "chat_row" => 列|null, "context" => 補好 chat_id／chat_sid 嘅 context]
    public function resolve_chat_row($context)
    {
        $existing_chat_row = $context["existing_chat_row"];

        // 判定：已有對話列 → 沿用；冇 → 由六欄建新列
        if ($existing_chat_row !== null) {
            $chat_row        = $existing_chat_row;
            $is_new_chat_row = false;
        } else {
            $chat_row        = $this->_insert_chat_row($context);
            $is_new_chat_row = true;
        }

        // 判定：冇對話列（建唔到）→ 失敗
        if ($chat_row === null) {
            return ["ok" => false, "chat_row" => null, "context" => $context];
        } else {
            $context["chat_sid"]        = $chat_row["chat_sid"];
            $context["chat_id"]         = $chat_row["chat_id"];
            $context["is_new_chat_row"] = $is_new_chat_row;

            return ["ok" => true, "chat_row" => $chat_row, "context" => $context];
        }
    }

    // 由 context 六欄建對話列（回列／null）
    private function _insert_chat_row($context)
    {
        // 六個欄位先落同名變數（標題草稿已算好——呢度只取，唔再計算）
        $chat_title       = (string)$context["chat_title"];
        $chat_sid         = (string)$context["chat_sid"];
        $provider_id      = (string)$context["provider_id"];
        $model_id         = (string)$context["model_id"];
        $reasoning_effort = (string)$context["reasoning_effort"];
        $chat_mode        = (string)$context["conf_chat_mode"];

        $chat_data                     = [];
        $chat_data["chat_sid"]         = $chat_sid;
        $chat_data["chat_title"]       = $chat_title;
        $chat_data["provider_id"]      = $provider_id;
        $chat_data["model_id"]         = $model_id;
        $chat_data["reasoning_effort"] = $reasoning_effort;
        $chat_data["chat_mode"]        = $chat_mode;

        return $this->insert_db_chat($context["project_id"], $chat_data);
    }
}
