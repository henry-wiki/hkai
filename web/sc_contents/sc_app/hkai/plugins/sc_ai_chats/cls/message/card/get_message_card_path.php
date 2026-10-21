<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card get_message_card_path：card 子域動作（trait——合成入 card_Class） ==
// == 路徑：messages/rows/{message_id}.json（與 polling／archive 同層；message_id 全域唯一——讀取唔需要 slug） ==

trait get_message_card_path
{
    // 卡檔路徑（單一檔＝一則訊息——message row ＋ responses 陣列）
    public function get_message_card_path($message_id)
    {
        return dirname(sc_ai_chats()->storage()->get_messages_dir()) . "/rows/" . (int)$message_id . ".json";
    }

    // 全域 response_id 序號檔（同表 AUTOINCREMENT 語義——flush 時帶返 DB，兩邊 id 一致）
    public function get_response_id_seq_path()
    {
        return dirname(sc_ai_chats()->storage()->get_messages_dir()) . "/rows/response_seq.txt";
    }
}
