<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue notify_image_extract：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：通知抽取器開工（單程——寫完即返，唔等結果；抽取器自己讀箱內 meta／attachments） ==
// == 上游：dispatch_polling_job（status=pending）；下游：bridge POST /image_extract/start/ ==

trait notify_image_extract
{
    // 通知抽取器（回 bool＝socket 有冇送出；成功後由呼叫端寫 notify.image_extractor 標記）
    public function notify_image_extract($poll_item)
    {
        $notify_payload = [];

        $notify_payload["message_id"]   = (int)$poll_item["message_id"];
        $notify_payload["chat_id"]      = (int)$poll_item["chat_id"];
        $notify_payload["chat_sid"]     = (string)$poll_item["chat_sid"];
        $notify_payload["project_slug"] = (string)$poll_item["project_slug"];

        return hkai()->process()->bridge()->post_fire_and_forget("/image_extract/start/", $notify_payload);
    }
}
