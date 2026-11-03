<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue move_queue_item_to_polling：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：整份搬（queue 項目目錄 → polling/<slug>/<chat_sid>/<prompt_id>）＋蓋 message_id＋狀態／log／AGENTS ==
// == 上游：dequeue_prompt_message_to_poll；下游：poll get_poll_dir／append_msg_status_by_event／log append_log ==

trait move_queue_item_to_polling
{
    // 搬目錄（內容零改寫——只加 message_id）
    public function move_queue_item_to_polling($queue_item)
    {
        $chat_id    = (int)$queue_item["chat_id"];
        $message_id = (int)$queue_item["message_id"];

        $poll_chat_dir  = sc_ai_chats()->poll()->get_poll_chat_dir($queue_item["project_slug"], $queue_item["chat_sid"]);
        $queue_chat_dir = sc_ai_chats()->queue()->get_queue_chat_dir($queue_item["project_slug"], $queue_item["chat_sid"]);

        $message_dir    = $poll_chat_dir . "/" . $queue_item["prompt_id"];
        $queue_item_dir = $queue_chat_dir . "/" . $queue_item["prompt_id"];

        // 父層（polling/<slug>/<chat_sid>）——rename 前必須存在；目標目錄本身唔可以預建
        sc_mkdir($poll_chat_dir);

        // 整份搬（Windows rename 唔可以覆蓋已存在目錄——正常唔會撞 prompt_id）
        $moved = @rename($queue_item_dir, $message_dir);

        // 判定：搬唔到 → 例外（唔可以喺唔存在嘅目錄寫 meta／log）
        if ($moved === false) {
            throw new \RuntimeException("polling move failed: " . $queue_item_dir);
        }

        // 入 polling 目錄 → 通知監視（chat／project gate——polling 數值即時重算）

        $attachments = (array)($queue_item["attachments"] ?? []);
        $attachments_dir = "";

        // 判定：有附件 → 移到永久附件庫（data/messages/attachments/<slug>/<sid>/<prompt_id>）
        if (count($attachments) > 0) {
            $attachments_dir = sc_get_config("hkai.messages_attachments_dir") . "/" . $queue_item["project_slug"] . "/" . $queue_item["chat_sid"] . "/" . $queue_item["prompt_id"];
            $attachments_store_parent = dirname($attachments_dir);
            sc_mkdir($attachments_store_parent);

            // 判定：搬唔到 → 留在箱內（記錄後備路徑，路徑仍然可用）
            if (@rename($message_dir . "/attachments", $attachments_dir) === false) {
                $attachments_dir = $message_dir . "/attachments";
            }
        }

        // 箱內狀態：有附件＝pending（等抽取完成）；冇＝polling（等 worker 開工）
        if (count($attachments) > 0) {
            $queue_item["status"] = "pending";
        } else {
            $queue_item["status"] = "polling";
        }

        // 箱內 meta（message_id 已由呼叫端蓋好——原樣寫入；attachments_dir＝附件實際位置）
        $queue_item["attachments_dir"] = $attachments_dir;
        sc_write_json($message_dir . "/meta.json", $queue_item);

        // 附件列（搬遷落地之後才寫 DB——訊息與附件同一刻成立；message_id 已由呼叫端蓋好）
        sc_ai_chats()->attachment()->insert_message_attachments(
            (int)$queue_item["project_id"],
            $chat_id,
            $message_id,
            $attachments_dir,
            $attachments
        );

        // 狀態＋起步 log（有附件＝先 preparing；冇＝直接 queued）
        $this->_write_message_start_state($chat_id, $message_id, $queue_item);

        // 專案規則（每訊息一份；專案無檔＝略過）
        $this->_copy_project_agents_file($message_dir, $queue_item);

        // 搬完之後排隊對話目錄可能變空 → 收走
        $left_dirs = @scandir($queue_chat_dir);

        // 判定：只剩 .／..（或讀唔到）→ 收目錄
        if (
            $left_dirs !== false
            && count($left_dirs) <= 2
        ) {
            @rmdir($queue_chat_dir);
        }
    }

    // 初始狀態＋log（附件未整理＝preparing；否則 queued）
    private function _write_message_start_state($chat_id, $message_id, $queue_item)
    {
        $conf_attachments = $queue_item["attachments"] ?? [];

        // 判定：有附件 → preparing（worker 跳過，整理完成才放行）
        if (count($conf_attachments) > 0) {
            sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "QUERY_PREPARING");
            sc_ai_chats()->poll()->log()->append_log($chat_id, $message_id, "attachments preparing", ["event" => "QUERY_PREPARING"]);

            return;
        }

        sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "QUERY_ACCEPTED");
        sc_ai_chats()->poll()->log()->append_log($chat_id, $message_id, "query started", ["event" => "QUERY_ACCEPTED"]);
    }

    // 複製專案 root AGENTS.md 入訊息目錄（agent 規則——每訊息一份）
    private function _copy_project_agents_file($message_dir, $queue_item)
    {
        $project_agents_file = $queue_item["project_path"] . "/AGENTS.md";

        // 判定：專案冇 AGENTS.md → 略過
        if (!is_file($project_agents_file)) {
            return;
        }

        @copy($project_agents_file, $message_dir . "/AGENTS.md");
    }
}
