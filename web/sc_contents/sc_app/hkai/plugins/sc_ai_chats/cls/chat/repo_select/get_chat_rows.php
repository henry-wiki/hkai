<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_select;

// == sc_ai_chats chat get_chat_rows：chat 域動作（trait——掛入 chat_Class；檔＝動作：對話列讀取——by chat_id／by chat_sid／by project_id） ==
// == 2026-09-29 三支讀取合併一支（原 get_chat_row／get_chat_row_by_sid／get_chat_rows——2026-09-27 自分檔收返） ==
// == 2026-10-04 加 get_archived_chat_rows（歸檔清單讀——/<project_slug>/archive/ 用） ==

trait get_chat_rows
{
    // 讀取單一對話列（依 chat_id）
    public function get_chat_row($chatId)
    {
        return sc_db()->connection("sqlite")->selectOne(
            "SELECT * FROM chats WHERE chat_id = ?",
            [$chatId]
        );
    }

    // 讀取單一對話列（依 chat_sid——URL 主鍵）
    public function get_chat_row_by_sid($chat_sid)
    {
        return sc_db()->connection("sqlite")->selectOne(
            "SELECT * FROM chats WHERE chat_sid = ?",
            [$chat_sid]
        );
    }

    // 讀取對話列（依 project_id——該專案下未歸檔嘅對話；更新時間新到舊＝顯示順序；同時間以 chat_id DESC 決勝）
    public function get_chat_rows($project_id)
    {
        $pdo = sc_db()->getPdo();
        $selectStmt = $pdo->prepare("SELECT * FROM chats WHERE project_id = ? AND chat_status = 'normal' ORDER BY chat_updated_at DESC, chat_id DESC");
        $selectStmt->execute([$project_id]);

        return $selectStmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // 讀取對話列（依 project_id——該專案下已歸檔嘅對話；歸檔清單來源；同排序）
    public function get_archived_chat_rows($project_id)
    {
        $pdo = sc_db()->getPdo();
        $selectStmt = $pdo->prepare("SELECT * FROM chats WHERE project_id = ? AND chat_status = 'archived' ORDER BY chat_updated_at DESC, chat_id DESC");
        $selectStmt->execute([$project_id]);

        return $selectStmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
