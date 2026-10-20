<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\action;

// == sc_ai_chats message repair_stale_message_status：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/action.php 分獨立檔（one file one function） ==

trait repair_stale_message_status
{

    //---------------------------------------------------------
    // 1. 掃 DB 仲係未完成嘅
    // 2. 目錄仲在＝唔郁
    // 3. 有歸檔＝抄返歸檔嗰條 status
    // 4. 兩樣都冇＝failed
    //---------------------------------------------------------
    public function repair_stale_message_status($chat_id)
    {
        $message_rows = sc_ai_chats()->message()->repo_select()->get_message_list($chat_id, 0);

        // 逐條對賬（只查非終態——終態無需修）
        foreach ($message_rows as $message_row_item) {
            $message_status = (string)$message_row_item["message_status"];
            $message_id     = (int)$message_row_item["message_id"];

            $is_terminal = sc_ai_chats()->fsm()->is_terminal_status($message_status);

            // 判定：終態 → 唔郁
            if ($is_terminal === true) {
                continue;
            }

            $poll_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);
            $has_dir  = is_dir($poll_dir);

            // 判定：現行 dir 仲在 → 正常鏈處理中 → 唔介入
            if ($has_dir === true) {
                continue;
            }

            $archived_status = sc_ai_chats()->message()->read_archived_message_status($chat_id, $message_id);

            // 判定：有歸檔 → 抄歸檔尾行（檔案＝SSOT）
            if ($archived_status !== "") {
                $repaired_status = $archived_status;
                $repair_reason   = "archived";
            // 判定：冇 dir、冇歸檔 → 未完成被中斷（保守終態）
            } else {
                $repaired_status = "failed";
                $repair_reason   = "no_files";
            }

            $repair_record = [];
            $repair_record["from"]        = $message_status;
            $repair_record["to"]          = $repaired_status;
            $repair_record["reason"]      = $repair_reason;
            $repair_record["repaired_at"] = date("Y-m-d H:i:s");

            sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $repaired_status);
            sc_ai_chats()->message()->repo_update()->update_db_message_status_repair_meta($message_id, $repair_record);

            // 診斷 log（審計：邊條、由咩、改成咩、點解）
            hkai__sc_runtime_io()->info("repair_stale_message_status: message_id=" . $message_id . " " . $message_status . " -> " . $repaired_status . " (" . $repair_reason . ")");
        }
    }

}
