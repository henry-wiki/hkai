<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message touch_upstream_times：上游時間同步（trait——掛入 message_Class；檔＝動作）
// == 職責：下游（response／timing／attachment）有變 → 同步 messages／chats／projects 更新時間
// == 規則：response → message.message_update_at → chat.chat_updated_at → project.chat_updated_at
// == 上游：upsert_db_message_response、update_db_message_*_timing、close_db_message_response_rows

trait touch_upstream_times
{
    // 由 message_id 向上同步（message → chat → project；逐句 UPDATE，零讀取）
    // $include_ancestors＝false 時只寫訊息層（polling 每拍寫檔——唔升 chat／project 時間）
    public function touch_upstream_times($message_id, $include_ancestors = true)
    {
        $current_time = date("Y-m-d H:i:s");
        $message_id   = (int)$message_id;

        // 訊息層
        sc_db()->statement("UPDATE messages SET message_update_at = ? WHERE message_id = ?", [$current_time, $message_id]);

        // 判定：唔升上游 → 只寫訊息層
        if ($include_ancestors === false) {
            return;
        }

        // 對話層
        sc_db()->statement(
            "UPDATE chats SET chat_updated_at = ? WHERE chat_id = (SELECT chat_id FROM messages WHERE message_id = ?)",
            [$current_time, $message_id]
        );

        // 專案層（projects.chat_updated_at＝專案最後對話活動）
        sc_db()->statement(
            "UPDATE projects SET chat_updated_at = ? WHERE project_id = (SELECT project_id FROM messages WHERE message_id = ?)",
            [$current_time, $message_id]
        );

        // 上游已改 → 通知監視（chat／project gate）
    }
}
