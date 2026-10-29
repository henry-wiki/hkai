<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue dispatch_agent_item：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：polling 箱——每次回填＋放行；通知 agent（notify.agent 標記 60 秒內有效） ==
// == 上游：dispatch_polling_item；下游：backfill_extract_results／append_msg_status_by_event／notify_prompt_agent ==

trait dispatch_agent_item
{
    // 標記有效期（秒——過期即當「未通知」重試）
    private const NOTIFY_AGENT_MARKER_FRESH_SEC = 60;

    // 回填＋放行＋通知 agent（回 bool＝今次有冇送出通知）
    public function dispatch_agent_item($poll_item, $box_dir)
    {
        $attachments = (array)($poll_item["attachments"] ?? []);

        $prepared_marker = $box_dir . "/prepared.attachments";

        $has_attachments = count($attachments) > 0;

        // 判定：有附件 → 回填 DB（content／status）＋放行（每次都回填；放行只寫一次）
        if ($has_attachments === true) {
            sc_ai_chats()->attachment()->backfill_extract_results((int)$poll_item["message_id"], $box_dir);

            $is_prepared = is_file($prepared_marker);

            // 判定：未放行過 → 寫放行狀態一次＋落標記
            if ($is_prepared === false) {
                sc_ai_chats()->poll()->append_msg_status_by_event((int)$poll_item["chat_id"], (int)$poll_item["message_id"], "ATTACHMENTS_PREPARED");
                touch($prepared_marker);
            }
        }

        $notify_marker = $box_dir . "/notify.agent";

        $is_marker_fresh = false;

        // 判定：有標記 → 計年齡
        if (is_file($notify_marker)) {
            $marker_age_sec = time() - (int)@filemtime($notify_marker);

            // 判定：喺有效期內 → 當已通知
            if ($marker_age_sec <= self::NOTIFY_AGENT_MARKER_FRESH_SEC) {
                $is_marker_fresh = true;
            }
        }

        // 判定：標記新鮮 → 跳過通知（回填已做）
        if ($is_marker_fresh === true) {
            return false;
        }

        // 舊標記（過期／假成功）→ 清走，重新通知
        if (is_file($notify_marker)) {
            unlink($notify_marker);
        }

        // 開工前一刻：專案環境檔（缺檔先寫；已有＝唔動）
        $project_row = sc_ai_projects()->project()->get_project_row((int)$poll_item["project_id"]);
        sc_ai_projects()->tab_environment()->write_project_environment_file($project_row, false);

        $is_notified = sc_ai_chats()->queue()->notify_prompt_agent($poll_item);

        // 判定：送唔出（bridge 未起）→ 唔落標記（下拍再試）
        if ($is_notified === false) {
            return false;
        }

        touch($notify_marker);

        return true;
    }
}
