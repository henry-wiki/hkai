<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat get_latest_chat_sid：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/resolver.php 分獨立檔（one file one function） ==

trait get_latest_chat_sid
{

    // 動作二：專案最新一個對話 sid（依對話時間——最新嗰個；該專案冇對話 → 空字串）
    // 用家：對話清單解析（動作三）＋刪對話後嘅前端導向目標
    public function get_latest_chat_sid($project_id)
    {
        $latest_chat_row = sc_ai_chats()->chat()->get_latest_chat_row_by_project($project_id);

        // 判定：該專案冇對話 → 空字串（導向回專案根＝未選擇對話）
        if ($latest_chat_row === null) {
            return "";
        }

        return $latest_chat_row["chat_sid"];
    }

}
