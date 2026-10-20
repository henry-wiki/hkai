<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\archive;

// == sc_ai_chats message archive_msg_files：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/archive.php 分獨立檔（one file one function） ==

trait archive_msg_files
{
    //---------------------------------------------------------
    // 1. polling/ 個訊息目錄成個搬去 archive/
    //---------------------------------------------------------
    // archive：歸檔後嘅位置（歷史由嗰度讀返——history）
    public function archive_msg_files($chat_id, $message_id)
    {
        // 來源訊息目錄（messages/polling/{project_slug}/{message_id}）
        $message_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);

        // 判定：來源目錄唔存在（未建立／已歸檔）→ 唔動作
        if (!is_dir($message_dir)) {
            return;
        }

        // 歸檔目錄（archive/{project_slug}/{message_id}——同一個目錄名；歸檔側路徑單一來源）
        $archived_dir = sc_ai_chats()->poll()->get_archived_poll_dir($chat_id, $message_id);

        // 歸檔目錄上層（archive/{project_slug}/——唔存在＝建立；歸檔桶內未有 slug 夾時必先開）
        sc_mkdir(dirname($archived_dir));

        // 整個訊息目錄搬移（名稱照舊；daemon 尾寫可能短暫鎖檔——失敗唔噴警告）
        $is_message_dir_renamed = @rename($message_dir, $archived_dir);

        // 判定：搬移失敗（尾寫鎖檔）→ 等 1 秒重試最後一次（再失敗＝下輪 finalize 自動重試）
        if ($is_message_dir_renamed === false) {
            sleep(1);
            @rename($message_dir, $archived_dir);
        }

        // 歸檔後順手清理：清舊記錄檔（history_Class——cooldown 1 小時自守；同屬記錄生命週期）
        sc_ai_chats()->chat()->cleanup_old_logs();
    }

}
