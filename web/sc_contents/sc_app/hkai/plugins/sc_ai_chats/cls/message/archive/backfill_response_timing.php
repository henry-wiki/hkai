<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\archive;

// == sc_ai_chats message backfill_response_timing：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/archive.php 分獨立檔（one file one function） ==

trait backfill_response_timing
{

    //---------------------------------------------------------
    // 1. 攞各回應檔嘅時間
    // 2. 寫返入 message_response（一定要喺搬檔前）
    //---------------------------------------------------------
    public function backfill_response_timing($chat_id, $message_id)
    {
        // 摘要型別（檔名＝型別；檔案建立時間 → started_at；檔案修改時間 → finished_at）
        $response_type_list = ["summarize_thinking", "summarize_result"];

        // 逐型別回填 response 時間
        foreach ($response_type_list as $response_type_item) {
            $response_path = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, $response_type_item);

            // 判定：檔不存在 → 跳過
            if (
                $response_path === null
                || !is_file($response_path)
            ) {
                continue;
            }

            $response_started_at  = date("Y-m-d H:i:s", filectime($response_path));
            $response_finished_at = date("Y-m-d H:i:s", filemtime($response_path));

            sc_ai_chats()->message()->repo_update()->update_db_message_response_timing($message_id, $response_type_item, $response_started_at, $response_finished_at);
        }

        // 扁平兩型（過渡期——舊 worker 仍寫扁平）→ 對應 0 號索引列（DB 列＝response.0.*）
        $flat_type_index_map = [
            "response_thinking" => "response.0.thinking",
            "response_result"   => "response.0.answer",
        ];

        // 逐扁平型回填（檔案存在才寫；DB 列唔存在＝更新零列，無害）
        foreach ($flat_type_index_map as $flat_type => $indexed_type) {
            $flat_path = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, $flat_type);

            // 判定：檔不存在 → 跳過
            if (!is_file($flat_path)) {
                continue;
            }

            $response_started_at  = date("Y-m-d H:i:s", filectime($flat_path));
            $response_finished_at = date("Y-m-d H:i:s", filemtime($flat_path));

            sc_ai_chats()->message()->repo_update()->update_db_message_response_timing($message_id, $indexed_type, $response_started_at, $response_finished_at);
        }

        // 索引 response 檔（msg_response.{n}.{thinking|answer}.txt——逐檔回填；型別＝response.{n}.{kind}）
        $message_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);

        $indexed_files = array_merge(
            glob($message_dir . "/msg_response.*.thinking.txt") ?: [],
            glob($message_dir . "/msg_response.*.answer.txt") ?: []
        );

        // 逐索引檔回填
        foreach ($indexed_files as $indexed_file_item) {
            $indexed_file_parts = explode(".", basename($indexed_file_item));

            // 判定：檔名格式不符 → 跳過
            if (count($indexed_file_parts) < 4) {
                continue;
            }

            $indexed_type = "response." . (int)$indexed_file_parts[1] . "." . $indexed_file_parts[2];

            $response_started_at  = date("Y-m-d H:i:s", filectime($indexed_file_item));
            $response_finished_at = date("Y-m-d H:i:s", filemtime($indexed_file_item));

            sc_ai_chats()->message()->repo_update()->update_db_message_response_timing($message_id, $indexed_type, $response_started_at, $response_finished_at);
        }
    }

}
