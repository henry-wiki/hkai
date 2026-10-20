<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card read_message_card：card 子域動作（trait——合成入 card_Class） ==
// == 讀取＝SELECT * FROM message WHERE message_id = ?（card 存在＝串流中，讀源喺卡） ==

trait read_message_card
{
    // 讀卡（冇檔／讀唔到／JSON 壞 → null——呼叫端回落 DB）
    public function read_message_card($message_id)
    {
        $card_file = $this->get_message_card_path($message_id);

        // 判定：卡檔未建立 → null
        if (!is_file($card_file)) {
            return null;
        }

        $card_handle = fopen($card_file, "r");

        // 判定：開檔失敗 → null
        if ($card_handle === false) {
            return null;
        }

        // 共享鎖（等寫入完成——讀者永遠見到完整檔）
        flock($card_handle, LOCK_SH);

        $card_json = stream_get_contents($card_handle);

        flock($card_handle, LOCK_UN);
        fclose($card_handle);

        // 判定：讀唔到 → null
        if ($card_json === false) {
            return null;
        }

        $message_card = json_decode($card_json, true);

        // 判定：JSON 壞／唔係陣列 → null
        if (!is_array($message_card)) {
            return null;
        }

        return $message_card;
    }
}
