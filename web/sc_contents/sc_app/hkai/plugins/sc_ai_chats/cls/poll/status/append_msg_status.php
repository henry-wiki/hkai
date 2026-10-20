<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\status;

// == sc_ai_chats poll append_msg_status：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-27 自 poll/status.php 分獨立檔（one file one function） ==

trait append_msg_status
{

    // 追加 status（每次新增一行——最新狀態為最後一行）（私有——by_event 唯一入口）
    private function append_msg_status($chat_id, $message_id, $status)
    {
        $status_file = $this->get_poll_file($chat_id, $message_id, "status");
        file_put_contents($status_file, $status . "\n", FILE_APPEND | LOCK_EX);
    }

    // 依事件追加狀態（事件驅動的唯一寫入入口——fsm 解析、本 class 落地）
    // 事件一律由程序內部傳入——傳咩就落地對應 status，不另檢查
    // 同一粒 status 落兩處：檔案＝機器讀源；DB＝對外鏡像
    public function append_msg_status_by_event($chat_id, $message_id, $event)
    {
        // 由事件解析狀態（fsm——event 唯一決定）
        $next_status = sc_ai_chats()->fsm()->resolve_status_by_event($event);

        // 落地（檔案＋DB 同一粒 status——DB 經 message_repo_update）
        sc_ai_chats()->poll()->append_msg_status($chat_id, $message_id, $next_status);
        sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $next_status);

        return $next_status;
    }

}
