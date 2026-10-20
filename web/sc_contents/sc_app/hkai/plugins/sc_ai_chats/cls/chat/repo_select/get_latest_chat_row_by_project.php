<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_select;

// == sc_ai_chats chat get_latest_chat_row_by_project：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/repo_select.php 分獨立檔（one file one function） ==

trait get_latest_chat_row_by_project
{
    // 讀取專案最新一個對話列（依對話時間——最新嗰個；同清單同一 ORDER（chat_id DESC 平手 tiebreak——顯示順序 SSOT 在資料層）；未歸檔；該專案冇對話 → null）
    public function get_latest_chat_row_by_project($project_id)
    {
        return sc_db()->connection("sqlite")->selectOne(
            "SELECT * FROM chats WHERE project_id = ? AND chat_status = 'normal' ORDER BY chat_updated_at DESC, chat_id DESC LIMIT 1",
            [$project_id]
        );
    }

}
