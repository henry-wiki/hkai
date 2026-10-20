<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message repo_select get_message_rows：repo_select 子域動作（trait——合成入 repo_select_Class；檔＝動作：訊息讀取——全量／窗口／未收尾） ==
// == 2026-09-29 四支讀取合併一支（原 get_message_list／get_message_row／get_message_response_rows_by_message_id／get_non_terminal_message_rows——2026-09-27 自分檔收返） ==

trait get_message_rows
{
    // 讀取對話訊息（offset 制——message_index 起、message_limit 條；回傳正序（最舊在前）；窗口讀取改用家＝get_message_rows_window——本支淨低全量／唯讀用家（chat_delete／repair_stale_message_status））
    // $message_index＝窗口起點索引（由最舊數起；0 = 第一條）；$message_limit＝窗口條數（0 = 不限）
    public function get_message_list($chat_id, $message_index = 0, $message_limit = 0)
    {
        $pdo = sc_db()->getPdo();

        $select_sql     = "SELECT * FROM messages WHERE chat_id = ?";
        $select_bindings = [$chat_id];

        // 正序（最舊在前——索引即次序）
        $select_sql .= " ORDER BY message_id ASC";

        // 有上限 → 加 LIMIT＋OFFSET（由 message_index 起）
        if ($message_limit > 0) {
            $select_sql .= " LIMIT ? OFFSET ?";
            $select_bindings[] = (int)$message_limit;
            $select_bindings[] = (int)$message_index;
        }

        $selectStmt = $pdo->prepare($select_sql);
        $selectStmt->execute($select_bindings);

        return $selectStmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // 讀取對話訊息窗口（keyset——$message_id 帶＝更舊一頁（message_id < 游標）；null＝尾段最新窗口；多取一條（limit+1）交上層判 has_more；回傳正序（最舊在前））
    public function get_message_rows_window($chat_id, $message_id, $message_limit)
    {
        $pdo = sc_db()->getPdo();

        $select_sql      = "SELECT * FROM messages WHERE chat_id = ?";
        $select_bindings = [$chat_id];

        // 判定：帶咗游標 → 加更舊上界（message_id < 游標）
        if ($message_id !== null) {
            $select_sql .= " AND message_id < ?";
            $select_bindings[] = (int)$message_id;
        }

        // 由新到舊攞（多取一條——上層 count > limit 即仲有更舊）
        $select_sql .= " ORDER BY message_id DESC LIMIT ?";
        $select_bindings[] = (int)$message_limit + 1;

        $selectStmt = $pdo->prepare($select_sql);
        $selectStmt->execute($select_bindings);

        $message_rows = $selectStmt->fetchAll(\PDO::FETCH_ASSOC);

        // 反轉成正序（最舊在前——渲染次序同原窗口一致；多出嗰條（最舊）由呼叫端剔）
        return array_reverse($message_rows);
    }

    // 讀取跨對話非終態訊息（未收尾候選——終態詞彙由 fsm 域界定，唔喺 SQL 手抄）
    public function get_non_terminal_message_rows()
    {
        $terminal_status_list = sc_ai_chats()->fsm()->get_terminal_status_list();

        // 佔位符（每項一個 ?——清單長度由 fsm_map 決定）
        $placeholders = implode(", ", array_fill(0, count($terminal_status_list), "?"));

        $select_sql = "SELECT message_id, chat_id, message_status FROM messages";

        // 判定：有終態清單 → 加排除條件（唔係終態＝未收尾候選）
        if ($placeholders !== "") {
            $select_sql .= " WHERE message_status NOT IN (" . $placeholders . ")";
        }

        $pdo = sc_db()->getPdo();

        $selectStmt = $pdo->prepare($select_sql);
        $selectStmt->execute($terminal_status_list);

        return $selectStmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
