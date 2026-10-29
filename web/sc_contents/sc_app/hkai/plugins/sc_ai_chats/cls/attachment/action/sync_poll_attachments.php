<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment sync_poll_attachments：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：polling 箱回填 DB（content／status）＋首次寫 ATTACHMENTS_PREPARED（唔通知——通知由 stream 轉換驅動） ==
// == 上游：handle_message_poll（每拍逐箱）；下游：backfill_extract_results／append_msg_status_by_event ==

trait sync_poll_attachments
{
    // 單箱回填＋放行（回 bool＝今次有冇寫放行狀態）
    public function sync_poll_attachments($poll_item, $box_dir)
    {
        $attachments = (array)($poll_item["attachments"] ?? []);

        // 判定：冇附件 → 唔做
        if (count($attachments) < 1) {
            return false;
        }

        $message_status = (string)($poll_item["status"] ?? "");

        // 判定：未完成抽取（pending）→ 等抽取器，未回填
        if ($message_status === "pending") {
            return false;
        }

        sc_ai_chats()->attachment()->backfill_extract_results((int)$poll_item["message_id"], $box_dir);

        $prepared_marker = $box_dir . "/prepared.attachments";

        $is_prepared = is_file($prepared_marker);

        // 判定：已放行過 → 唔重寫狀態
        if ($is_prepared === true) {
            return false;
        }

        sc_ai_chats()->poll()->append_msg_status_by_event((int)$poll_item["chat_id"], (int)$poll_item["message_id"], "ATTACHMENTS_PREPARED");
        touch($prepared_marker);

        return true;
    }
}
