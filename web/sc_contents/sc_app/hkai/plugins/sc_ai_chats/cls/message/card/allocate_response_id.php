<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card allocate_response_id：card 子域動作（trait——合成入 card_Class） ==
// == 全域序號（等同 message_response 表 AUTOINCREMENT——flush 時帶返 DB，兩邊 response_id 完全一致） ==

trait allocate_response_id
{
    // 下一個 response_id（序號檔＋LOCK_EX——比表自增更輕）
    public function allocate_response_id()
    {
        $seq_file = $this->get_response_id_seq_path();

        // 目錄唔存在＝建立
        sc_mkdir(dirname($seq_file));

        $seq_handle = fopen($seq_file, "c+");

        // 判定：開檔失敗 → 回 0（呼叫端據此跳過寫入）
        if ($seq_handle === false) {
            return 0;
        }

        // 互斥鎖（序號唯一——同一刻只有一個發號者）
        flock($seq_handle, LOCK_EX);

        $seq_text      = stream_get_contents($seq_handle);
        $last_response = (int)trim((string)$seq_text);
        $next_response = $last_response + 1;

        ftruncate($seq_handle, 0);
        rewind($seq_handle);

        fwrite($seq_handle, (string)$next_response);

        fflush($seq_handle);

        flock($seq_handle, LOCK_UN);
        fclose($seq_handle);

        return $next_response;
    }
}
