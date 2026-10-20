<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card delete_message_card：card 子域動作（trait——合成入 card_Class） ==
// == 刪卡：① 終態 flush 入 DB 之後 ② 訊息刪除時（卡係熱存——DB 已係真相） ==

trait delete_message_card
{
    // 刪卡（唔存在＝唔動作）
    public function delete_message_card($message_id)
    {
        $card_file = $this->get_message_card_path($message_id);

        // 判定：卡檔唔存在 → 唔動作
        if (!is_file($card_file)) {
            return;
        }

        unlink($card_file);
    }
}
