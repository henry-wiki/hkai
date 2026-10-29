<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\guard;

// == sc_ai_chats prompt assert_queue_capacity：prompt 域守衛（trait——掛入 prompt_Class；cls/prompt/guard/） ==
// == 職責：等待列上限（該對話最多 3 條）——滿即寫 E_QUEUE_LIMIT；順帶交回既有對話列 ==
// == 上游：prompt_send()->prepare_prompt_request；下游：chat get_chat_row_by_sid／queue get_queue_items ==

trait assert_queue_capacity
{
    // 工序 4：queue 上限（滿即拒收 429）
    public function assert_queue_capacity($context)
    {
        $existing_chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($context["chat_sid"]);
        $queue_items       = [];

        // 判定：既有對話 → 讀該對話 queue（等待列）
        if ($existing_chat_row !== null) {
            $queue_items = sc_ai_chats()->queue()->get_queue_items($context["project_slug"], $context["chat_sid"]);
        }

        // 判定：等待列已滿（≥3）→ 記錄錯誤（429）
        if (count($queue_items) >= 3) {
            $context["error"] = [
                "ok"      => false,
                "code"    => "E_QUEUE_LIMIT",
                "title"   => hkai()->i18n()->get("queue.limit_title"),
                "message" => hkai()->i18n()->get("queue.limit_message"),
            ];

            $context["error_status"] = 429;
        }

        $context["existing_chat_row"] = $existing_chat_row;

        return $context;
    }
}
