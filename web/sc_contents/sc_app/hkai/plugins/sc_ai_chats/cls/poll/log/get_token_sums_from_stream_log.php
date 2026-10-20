<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\log;

// == sc_ai_chats poll\log get_token_sums_from_stream_log：log 子域動作（trait——直接合成入 poll_Class（log 子域）；檔＝動作） ==
// == 2026-09-27 自 cls/poll/get_token_sums_from_stream_log.php 搬入（log 子域） ==

trait get_token_sums_from_stream_log
{
    // 讀 msg_stream_log 的 usage → 取「最後一筆」（＝主回覆；摘要在前，故最後一筆必為主請求）
    // 為什麼只取最後一筆：一次流程會有「摘要請求 ＋ 主回覆請求」兩筆 usage，
    //   加總會把摘要算進來（使用者要的是「回覆本身」的 tokens——對應 OpenRouter 顯示值）
    public function get_token_sums_from_stream_log($chat_id, $message_id)
    {
        $token_sums = [];
        $token_sums["sent"]     = 0;
        $token_sums["received"] = 0;

        $stream_log_path = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "stream_log");

        // 判定：stream_log 唔存在 → 回 0
        if (!is_file($stream_log_path)) {
            return $token_sums;
        }

        $stream_lines = file($stream_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 逐行解析 usage（持續覆蓋 → 迴圈結束即為最後一筆）
        foreach ($stream_lines as $stream_line_item) {
            $stream_record = json_decode($stream_line_item, true);

            // 判定：無法解析 → 跳過
            if (!is_array($stream_record)) {
                continue;
            }
            // 判定：無 usage → 跳過
            if (!isset($stream_record["usage"])) {
                continue;
            }

            $token_sums["sent"]     = (int)$stream_record["usage"]["prompt_tokens"];
            $token_sums["received"] = (int)$stream_record["usage"]["completion_tokens"];
        }

        return $token_sums;
    }
}
