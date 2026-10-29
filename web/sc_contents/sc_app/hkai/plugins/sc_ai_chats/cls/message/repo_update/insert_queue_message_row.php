<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update insert_queue_message_row：message 域寫入（trait——掛入 repo_update_Class） ==
// == 職責：排隊箱（meta.json）→ messages 表一列（model_name 拆 provider／model；content＝prompt_text） ==
// == 上游：queue dequeue_prompt_message_to_poll；下游：insert_db_message ==

trait insert_queue_message_row
{
    // 寫 DB 訊息列（回 message row；失敗＝null）
    public function insert_queue_message_row($queue_item)
    {
        $chat_id    = (int)$queue_item["chat_id"];
        $project_id = (int)$queue_item["project_id"];
        $content    = (string)($queue_item["prompt_text"] ?? "");
        $prompt_id  = (string)$queue_item["prompt_id"];

        // 模型（箱內已分開兩欄——直接用，唔砌 model_name 再拆）
        $provider_id      = (string)$queue_item["provider_id"];
        $model_id         = (string)$queue_item["model_id"];
        $reasoning_effort = (string)($queue_item["reasoning_effort"] ?? "");

        // 對話模式正規化（同 chats 列同一值）
        $chat_mode      = (string)$queue_item["chat_mode"];
        $conf_chat_mode = sc_ai_chats()->chat_option()->get_chat_mode($chat_mode);

        $message_model_info = [];
        $message_model_info["chat_mode"]        = $conf_chat_mode;
        $message_model_info["provider_id"]      = $provider_id;
        $message_model_info["model_id"]         = $model_id;
        $message_model_info["reasoning_effort"] = $reasoning_effort;

        return sc_ai_chats()->message()->repo_update()->insert_db_message($project_id, $chat_id, $content, $message_model_info, $prompt_id);
    }
}
