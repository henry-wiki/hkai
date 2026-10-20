<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card update_message_card_status：card 子域動作（trait——合成入 card_Class） ==
// == 同 DB 版 update_db_message_status 同語義（message_status＋message_update_at）；對話四桶計數留待終態 flush ==

trait update_message_card_status
{
    // 寫卡：訊息狀態（卡未建立＝由 DB 讀 message 列開卡）
    public function update_message_card_status($message_id, $status)
    {
        $current_time = date("Y-m-d H:i:s");

        $message_card = $this->read_message_card($message_id);

        // 判定：卡未建立 → 由 DB 讀 message 列開卡
        if ($message_card === null) {
            $message_row = sc_db()->connection("sqlite")->selectOne("SELECT * FROM messages WHERE message_id = ?", [$message_id]);

            // 判定：DB 都冇此訊息 → 唔寫
            if ($message_row === null) {
                return;
            }

            // 判定：DB 訊息已終態（finalize 已 flush 完）→ 唔再開卡（防終態後殘卡）
            if (sc_ai_chats()->fsm()->is_terminal_status($message_row["message_status"])) {
                return;
            }

            $message_card = [];
            $message_card["table"]     = "message";
            $message_card["message"]   = $message_row;
            $message_card["responses"] = [];
        }

        // 同 DB UPDATE 同一組欄位
        $message_card["message"]["message_status"]    = $status;
        $message_card["message"]["message_update_at"] = $current_time;

        $this->write_message_card($message_id, $message_card);
    }
}
