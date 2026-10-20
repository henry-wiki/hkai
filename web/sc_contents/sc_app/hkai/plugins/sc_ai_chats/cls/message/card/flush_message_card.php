<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card flush_message_card：card 子域動作（trait——合成入 card_Class） ==
// == 終態最後一次寫入：一次 transaction 將卡嘅 responses flush 入 SQLite（message 狀態由 finalize ④ 統一處理） ==
// == response_id 帶住明插（序號＝全域自增）——檔案同 DB 兩邊 id 完全一致 ==

trait flush_message_card
{
    // 卡 flush 入 DB（responses 明插；訊息狀態／四桶計數＝finalize ④ update_db_message_status 單一入口）
    public function flush_message_card($message_card, $status)
    {
        $current_time = date("Y-m-d H:i:s");

        // 段收邊目標（同 upsert 同一規則）
        if ($status === "completed") {
            $closed_response_status = "completed";
        } else {
            $closed_response_status = "aborted";
        }

        $connection = sc_db()->connection("sqlite");

        $connection->transaction(function ($db) use ($message_card, $closed_response_status) {
            // 逐段 response row（明插 response_id——兩邊 id 一致；同 key 已存在＝整行覆蓋）
            foreach ($message_card["responses"] as $response_row_item) {
                $response_status = (string)$response_row_item["response_status"];

                // 判定：仲係 poll → 按訊息終態收邊
                if ($response_status === "poll") {
                    $response_status = $closed_response_status;
                }

                $db->statement(
                    "INSERT OR REPLACE INTO message_response"
                    . " (response_id, project_id, chat_id, message_id, poll_file, response_type, response_index,"
                    . " response_content, response_status, response_started_at, response_finished_at,"
                    . " response_created_at, response_updated_at)"
                    . " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        (int)$response_row_item["response_id"],
                        (int)$response_row_item["project_id"],
                        (int)$response_row_item["chat_id"],
                        (int)$response_row_item["message_id"],
                        (string)($response_row_item["poll_file"] ?? ""),
                        (string)$response_row_item["response_type"],
                        (int)$response_row_item["response_index"],
                        (string)$response_row_item["response_content"],
                        $response_status,
                        (string)$response_row_item["response_started_at"],
                        (string)$response_row_item["response_finished_at"],
                        (string)$response_row_item["response_created_at"],
                        (string)$response_row_item["response_updated_at"],
                    ]
                );
            }
        });

        return true;
    }
}
