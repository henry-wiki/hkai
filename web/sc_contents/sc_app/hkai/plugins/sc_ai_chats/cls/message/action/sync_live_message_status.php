<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\action;

// == sc_ai_chats message sync_live_message_status：message 域動作（trait——掛入 message_Class；檔＝動作） ==

trait sync_live_message_status
{

    //---------------------------------------------------------
    // 對賬 DB 鏡像 ← 輪詢目錄狀態檔（只理非終態列——檔案＝SSOT）
    // 同場就地更新傳入列（同一回合嘅回應即刻準確——DB 改咗但列陣列仍係讀取前嘅舊值）
    // 上游：message_list_fetch（返回對話即刻對賬——令吐出嘅狀態跟實時）
    // 下游：poll file get_poll_status（讀狀態檔尾行）／repo_update update_db_message_status（寫鏡像）
    //---------------------------------------------------------
    public function sync_live_message_status(array &$message_rows)
    {
        // 逐條列對賬（終態＝DB 已係最終值，唔理）
        foreach ($message_rows as $message_index => $message_row_item) {
            $message_status  = (string)($message_row_item["message_status"] ?? "");
            $message_id      = (int)($message_row_item["message_id"] ?? 0);
            $message_chat_id = (int)($message_row_item["chat_id"] ?? 0);

            $is_terminal = sc_ai_chats()->fsm()->is_terminal_status($message_status);

            // 判定：終態 → 唔郁
            if ($is_terminal === true) {
                continue;
            }

            $live_status = sc_ai_chats()->poll()->file()->get_poll_status($message_chat_id, $message_id);

            // 判定：目錄讀唔到狀態（未派工／已歸檔）→ 唔郁（歸檔對賬交 repair_stale_message_status）
            if ($live_status === "") {
                continue;
            }

            // 判定：目錄狀態同 DB 鏡像一樣 → 唔郁
            if ($live_status === $message_status) {
                continue;
            }

            sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $live_status);

            // 就地更新本窗列（本次回應 render 即刻用新值——只改 DB 嘅話，本回合仍吐舊狀態）
            $message_rows[$message_index]["message_status"] = $live_status;

            // 診斷 log（審計：邊條、由咩、改成咩——鏡像落後追返實時）
            hkai()->runtime()->logger()->info("sync_live_message_status: message_id=" . $message_id . " " . $message_status . " -> " . $live_status);
        }
    }

}
