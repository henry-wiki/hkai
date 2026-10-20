<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message repo_select get_message_response_rows_by_message_id：repo_select 子域動作（trait——合成入 repo_select_Class；檔＝動作：回應列批次讀取） ==
// == 2026-10-08 排序改 DB record 序（response_id）——寫入序＝thinking→result 強制正序；修「同一秒寫入」批次出現時段位反轉 ==

trait get_message_response_rows_by_message_id
{
    // 讀取指定訊息集合的回應列（卡優先；其餘 DB 一次過查——response_id 正序）
    public function get_message_response_rows_by_message_id($message_ids)
    {
        // 空清單 → 回空（不查詢）
        if (count($message_ids) === 0) {
            return [];
        }

        $response_rows       = [];
        $missing_message_ids = [];

        // 逐個 message_id：卡有 → 直接收；卡冇 → 留返查 DB
        foreach ($message_ids as $message_id_item) {
            $message_card = sc_ai_chats()->message()->card()->read_message_card($message_id_item);

            // 判定：冇卡 → 交 DB 批次查
            if ($message_card === null) {
                $missing_message_ids[] = $message_id_item;

                continue;
            }

            // 逐 row 收入（卡內已按寫入序）
            foreach ($message_card["responses"] as $card_response_row_item) {
                $response_rows[] = $card_response_row_item;
            }
        }

        // 判定：全部有卡 → 唔使查 DB
        if (count($missing_message_ids) === 0) {
            return $response_rows;
        }

        $pdo = sc_db()->getPdo();

        // 依 message_id 逐個佔位（IN 查詢——僅取窗口內）
        $placeholders = implode(",", array_fill(0, count($missing_message_ids), "?"));
        $bindings     = [];
        foreach ($missing_message_ids as $message_id_item) {
            $bindings[] = $message_id_item;
        }

        $selectStmt = $pdo->prepare(
            "SELECT * FROM message_response WHERE message_id IN (" . $placeholders . ") 
            ORDER BY message_id ASC, response_id ASC"
        );
        $selectStmt->execute($bindings);

        $db_response_rows = $selectStmt->fetchAll(\PDO::FETCH_ASSOC);

        // DB rows 併入（呼叫端逐 message 用——排序由 response_id 保證）
        foreach ($db_response_rows as $db_response_row_item) {
            $response_rows[] = $db_response_row_item;
        }

        return $response_rows;
    }

}
