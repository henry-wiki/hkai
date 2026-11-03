<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\status;

// == sc_ai_chats poll append_msg_status：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_console/commands/status.php 分獨立檔（one file one function） ==

trait append_msg_status
{

    // 追加 status（每次新增一行——最新狀態為最後一行）（私有——by_event 唯一入口）
    public function append_msg_status($chat_id, $message_id, $status, $box_dir = "")
    {
        // 判定：呼叫端已帶箱路徑（身份直組）→ 零 DB；冇帶 → 用 chat_id／message_id 反查
        if ($box_dir === "") {
            $box_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);
        }

        $status_file = $box_dir . "/msg_status.txt";
        $status_dir  = dirname($status_file);

        // 判定：箱已刪（目錄唔存在——刪訊息／歸檔競態）→ 記 log、唔寫、回 false
        if (is_dir($status_dir) === false) {
            hkai()->runtime()->logger()->warn("append_msg_status skipped: box dir missing | message_id=" . (int)$message_id . " | path=" . $status_file);
            return false;
        }

        $written_bytes = file_put_contents($status_file, $status . "\n", FILE_APPEND | LOCK_EX);

        // 判定：寫入失敗（權限／磁碟）→ 記 log、唔寫 meta、回 false
        if ($written_bytes === false) {
            hkai()->runtime()->logger()->error("append_msg_status failed: write error | message_id=" . (int)$message_id . " | path=" . $status_file);
            return false;
        }

        // 箱內 meta.status：polling 內嘅中間詞換成階段詞（queued→polling；preparing→pending）
        $meta_status = $status;

        // 判定：worker 未開工（queued）→ 箱階段＝polling
        if ($status === "queued") {
            $meta_status = "polling";
        }

        // 判定：附件整理中（preparing）→ 箱階段＝pending
        if ($status === "preparing") {
            $meta_status = "pending";
        }

        sc_ai_chats()->message()->set_message_meta_status($box_dir, $meta_status);
    }

    // 依事件追加狀態（事件驅動的唯一寫入入口——fsm 解析、本 class 落地）
    // 事件一律由程序內部傳入——傳咩就落地對應 status，不另檢查
    // 同一粒 status 落兩處：檔案＝機器讀源；DB＝對外鏡像
    public function append_msg_status_by_event($chat_id, $message_id, $event, $box_dir = "")
    {
        // 由事件解析狀態（fsm——event 唯一決定）
        $next_status = sc_ai_chats()->fsm()->resolve_status_by_event($event);

        // 落地（檔案＋DB 同一粒 status——DB 經 message_repo_update）
        $appended = sc_ai_chats()->poll()->append_msg_status($chat_id, $message_id, $next_status, $box_dir);

        // 判定：檔案寫唔入（箱已刪／寫入失敗）→ 唔鏡像 DB（避免孤兒狀態）
        if ($appended === false) {
            return $next_status;
        }

        sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $next_status);

        return $next_status;
    }

}
