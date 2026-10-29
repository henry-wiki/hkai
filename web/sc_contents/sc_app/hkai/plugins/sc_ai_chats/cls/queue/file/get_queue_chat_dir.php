<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue get_queue_chat_dir：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：排隊對話目錄（messages/queue/<chat_sid>——每對話一個；入面每條 pending 一目錄） ==
// == 上游：append／get／remove／delete／shift；下游：sc_config hkai.messages_queue_dir＋chat get_chat_row ==

trait get_queue_chat_dir
{
    // 排隊對話目錄（queue/<project_slug>/<chat_sid>——直接由身份砌，免查 DB）
    public function get_queue_chat_dir($project_slug, $chat_sid)
    {
        // 排隊根（sc_config 單一來源）
        $messages_queue_dir = sc_get_config("hkai.messages_queue_dir");

        return $messages_queue_dir . "/" . $project_slug . "/" . $chat_sid;
    }
}
