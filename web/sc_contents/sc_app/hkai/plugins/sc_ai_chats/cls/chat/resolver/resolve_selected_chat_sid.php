<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat resolve_selected_chat_sid：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/resolver.php 分獨立檔（one file one function） ==

trait resolve_selected_chat_sid
{

    // 解析「目前對話」（清單高亮用；冇有效對話可指 → null）
    // 次序：動作一 caller 傳入（查證存在且屬呢個專案；指定咗但無效 → 即回 null——唔兜底）／未指定（URL 冇對話段）→ 一律 null（唔揀任何一個——缺 chat_sid ≠ 自動揀）
    public function resolve_selected_chat_sid($project_row, $selected_chat_sid)
    {
        $project_id = (int)$project_row["project_id"];

        // 動作一：caller 傳入嘅對話——查證存在且屬呢個專案 → 採用
        if (
            $selected_chat_sid !== null
            && $selected_chat_sid !== ""
        ) {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($selected_chat_sid);

            // 判定：查得到、屬呢個專案、而且未歸檔 → 採用（歸檔＝清單唔載——當未選中）
            if (
                $chat_row !== null
                && (int)$chat_row["project_id"] === $project_id
                && $chat_row["chat_status"] === "normal"
            ) {
                return $selected_chat_sid;
            }

            // 判定：caller 有指定但唔有效（唔存在／唔屬呢個專案）→ null（唔兜底拉去其他對話——清單照出、冇一個 active）
            return null;
        }

        // 未指定（URL 冇對話段）→ null——唔揀任何一個（缺 chat_sid ≠ 自動揀）
        return null;
    }

}
