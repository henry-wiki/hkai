<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card write_message_card：card 子域動作（trait——合成入 card_Class） ==
// == 寫入＝互斥鎖＋整檔覆寫（同表 transaction 語義：一齊成／一齊敗） ==

trait write_message_card
{
    // 寫卡（LOCK_EX＋整檔覆寫；回傳 bool）
    public function write_message_card($message_id, $message_card)
    {
        $card_file = $this->get_message_card_path($message_id);
        $card_dir  = dirname($card_file);

        // 目錄唔存在＝建立
        sc_mkdir($card_dir);

        $card_json = json_encode($message_card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 判定：編碼失敗 → false
        if ($card_json === false) {
            return false;
        }

        $card_handle = fopen($card_file, "c");

        // 判定：開檔失敗 → false
        if ($card_handle === false) {
            return false;
        }

        // 互斥鎖（同時只有一個寫入者——同 transaction 等鎖語義）
        flock($card_handle, LOCK_EX);

        ftruncate($card_handle, 0);
        rewind($card_handle);

        $write_bytes = fwrite($card_handle, $card_json);

        fflush($card_handle);

        flock($card_handle, LOCK_UN);
        fclose($card_handle);

        // 判定：寫入失敗 → false
        if ($write_bytes === false) {
            return false;
        }

        return true;
    }
}
