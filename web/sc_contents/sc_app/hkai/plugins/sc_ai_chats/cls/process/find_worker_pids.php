<?php
// == sc_ai_chats/cls/process/find_worker_pids.php：process 域動作（trait——掛入 process_Class；檔＝動作） ==
// == 職責：find_worker_pids——台帳搵指定訊息嘅 worker pids（純查詢：chat_id＋message_id 過濾、只回 pid——最快速） ==
namespace sc\app\hkai\plugins\sc_ai_chats\process;

trait find_worker_pids
{

    // 搵指定訊息嘅 worker pids（process_list.jsonl——append-only JSONL：name=worker ＋ chat_id ＋ message_id 三中即收）
    // 純查詢零格筛（最快速）——死活／防誤殺交殺方（kill_process）處理
    public function find_worker_pids($chat_id, $message_id)
    {
        $process_list_path = hkai__core()->get_process_list_path();
        $chat_id_str       = strval($chat_id);
        $message_id_str    = strval($message_id);

        // 判定：台帳檔唔存在 → 空
        if (!is_file($process_list_path)) {
            return [];
        }

        $worker_pids = [];

        // 讀台帳行落變數（先讀、後迴圈——中間可 print 做 debug）
        $process_list_lines = file($process_list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 判定：讀唔到 → 當空（維持「回傳必為陣列」）
        if ($process_list_lines === false) {
            $process_list_lines = [];
        }

        // 逐行（append-only JSONL——搵 worker 記錄：name＋chat_id＋message_id 全中）
        foreach ($process_list_lines as $process_list_line_item) {
            $process_record = json_decode($process_list_line_item, true);

            // 判定：非合法 JSON → 跳過
            if (!is_array($process_record)) {
                continue;
            }

            // 判定：非 worker 記錄 → 跳過
            if (($process_record["name"] ?? "") !== "worker") {
                continue;
            }

            // 判定：chat_id 唔中 → 跳過
            if (strval($process_record["chat_id"] ?? "") !== $chat_id_str) {
                continue;
            }

            // 判定：message_id 唔中 → 跳過
            if (strval($process_record["message_id"] ?? "") !== $message_id_str) {
                continue;
            }

            $worker_pid = (int)($process_record["pid"] ?? 0);

            // 判定：pid 無效 → 跳過
            if ($worker_pid < 1) {
                continue;
            }

            $worker_pids[] = $worker_pid;
        }

        return $worker_pids;
    }
}
