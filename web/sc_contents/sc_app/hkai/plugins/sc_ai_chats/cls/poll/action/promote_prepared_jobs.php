<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll promote_prepared_jobs：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：preparing 嘅訊息——抽取完成即回填附件內容、轉 queued、寫 log、通知 bridge ==
// == 上游：handle_message_poll（每拍監視）；下游：read_extract_state／attachment.save_extract_result／bridge.notify

trait promote_prepared_jobs
{

    //---------------------------------------------------------
    // 1. 掃本對話進行中訊息
    // 2. 只處理 preparing
    // 3. 抽取完成（done／done_with_errors）→ 逐張回填附件（content＋status）
    // 4. 轉 queued（ATTACHMENTS_PREPARED）＋寫活動 log
    // 5. 通知 bridge（worker 可以開工）
    //---------------------------------------------------------
    public function promote_prepared_jobs($chat_id)
    {
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs($chat_id);
        $storage_dir  = sc_ai_chats()->storage()->get_storage_dir();

        foreach ($polling_jobs as $polling_job_item) {
            $message_status = (string)($polling_job_item["status"] ?? "");

            // 判定：唔係 preparing → 唔關事
            if ($message_status !== "preparing") {
                continue;
            }

            $message_id    = (int)($polling_job_item["message_id"] ?? 0);
            $extract_state = $this->read_extract_state($message_id);

            // 判定：未完成（running／pending／cancelled）→ 下一拍再睇
            if (!in_array($extract_state["state"], ["done", "done_with_errors"], true)) {
                continue;
            }

            $ready_count = 0;

            // 逐張回填附件（摘要文字＋狀態）
            foreach ($extract_state["files"] as $extract_file_item) {
                $file_state = (string)($extract_file_item["state"] ?? "pending");
                $meta_path  = (string)($extract_file_item["meta_path"] ?? "");
                $summary_text = "";

                // 判定：meta 檔存在 → 讀摘要
                if ($meta_path !== "" && is_file($meta_path)) {
                    $meta_data = json_decode(@file_get_contents($meta_path), true);

                    // 判定：meta 正常 → 取 summary_text
                    if (is_array($meta_data)) {
                        $summary_text = (string)($meta_data["summary_text"] ?? "");
                    }
                }

                $attachment_status = "ready";

                // 判定：該張失敗 → 標 failed
                if ($file_state === "failed") {
                    $attachment_status = "failed";
                }

                // 絕對路徑 → DB 相對路徑（storage dir 之後嗰段）
                $absolute_path = (string)($extract_file_item["path"] ?? "");
                $relative_path = $absolute_path;

                // 判定：路徑以 storage dir 開頭 → 切走前綴
                if (strpos($absolute_path, $storage_dir) === 0) {
                    $relative_path = ltrim(substr($absolute_path, strlen($storage_dir)), "/");
                }

                sc_ai_chats()->attachment()->save_extract_result($message_id, $relative_path, $summary_text, $attachment_status);

                // 判定：成功 → 計數
                if ($attachment_status === "ready") {
                    $ready_count++;
                }
            }

            // 放行（queued）＋對外鏡像（DB）
            sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "ATTACHMENTS_PREPARED");

            sc_ai_chats()->poll()->log()->append_log(
                $chat_id,
                $message_id,
                "attachments prepared",
                [
                    "event"    => "ATTACHMENTS_PREPARED",
                    "metadata" => [
                        "done"  => $ready_count,
                        "total" => (int)$extract_state["total"],
                    ],
                ]
            );

            // 喚醒目標（訊息落地時蓋章嘅 agent——payload 帶過去，bridge 唔靠全局值）
            $conf_data  = sc_ai_chats()->poll()->file()->get_msg_conf_data($chat_id, $message_id);
            $agent      = (string)($conf_data["agent"] ?? "");

            // 通知 bridge（worker 掃描見 queued 即開工）
            hkai()->process()->bridge()->notify_server_new_prompt($chat_id, $message_id, $agent);
        }
    }
}
