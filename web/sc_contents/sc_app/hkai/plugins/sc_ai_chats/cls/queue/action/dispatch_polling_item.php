<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue dispatch_polling_item：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：單箱路由——pending → dispatch_image_extract_item；polling → dispatch_agent_item ==
// == 上游：dispatch_polling_job（逐箱）；下游：本域兩支分支動作 ==

trait dispatch_polling_item
{
    // 單箱派工（$poll_item＝箱內 meta；回 bool＝今次有冇送出通知）
    public function dispatch_polling_item($poll_item)
    {
        $box_dir = sc_ai_chats()->poll()->get_existing_message_dir((int)$poll_item["message_id"]);

        // 判定：箱唔存在（已歸檔）→ 唔派
        if ($box_dir === "") {
            return false;
        }

        $message_status = (string)($poll_item["status"] ?? "");

        // 判定：等抽取 → 交抽取分支
        if ($message_status === "pending") {
            return sc_ai_chats()->queue()->dispatch_image_extract_item($poll_item, $box_dir);
        }

        // 判定：等開工 → 交 agent 分支（回填＋放行＋通知）
        if ($message_status === "polling") {
            //return sc_ai_chats()->queue()->dispatch_agent_item($poll_item, $box_dir);
        }

        // 其餘狀態（replying／終態）→ 唔插手
        return false;
    }
}
