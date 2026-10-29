<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue notify_prompt_agent：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：通知 agent 開工回覆（單程——寫完即返，唔等生成；狀態由 worker 寫檔，UI 靠 message_poll 讀） ==
// == 上游：dispatch_polling_job（status=polling）；下游：bridge POST /daemon/add_message/ ==

trait notify_prompt_agent
{
    // 通知 agent（回 bool＝socket 有冇送出；成功後由呼叫端寫 notify.agent 標記）
    public function notify_prompt_agent($poll_item)
    {
        $notify_payload = [];

        $notify_payload["chat_id"]    = (int)$poll_item["chat_id"];
        $notify_payload["message_id"] = (int)$poll_item["message_id"];
        $notify_payload["agent"]      = (string)hkai()->agent()->get_agent();

        return hkai()->process()->bridge()->post_fire_and_forget("/daemon/add_message/", $notify_payload);
    }
}
