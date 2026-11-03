<?php

namespace sc\app\hkai\plugins\sc_stream\snapshot\cleanup;

// == cleanup：清理子域（trait——掛入 snapshot_Class） ==
// == 職責：清理逾時檔案（唔參與任何讀寫狀態）
// == 上游：sse_state（每 tick 叫；函數自行計 60 秒節流）
// == 下游：data/stream/<target>/view/<view_id>.json

trait cleanup
{
    public function sweep_expired_view_files()
    {
        $stream_dir      = sc_get_config("hkai.stream_dir");
        $sweep_marker    = $stream_dir . "/.last_sweep";
        $expired_seconds = 300;
        $removed_count   = 0;
        $now_time        = time();

        // 節流：marker mtime 未夠 60 秒 → 今次唔掃（同 cleanup_old_logs 同一套冷卻寫法）
        if (is_file($sweep_marker) === true) {
            $marker_age = $now_time - (int)@filemtime($sweep_marker);

            // 判定：冷卻期內 → 跳過
            if ($marker_age < 60) {
                return 0;
            }
        }

        $view_paths = glob($stream_dir . "/*/view/*.json");

        // 判定：glob 失敗 → 用空陣列
        if (is_array($view_paths) === false) {
            $view_paths = [];
        }

        $all_viewer_paths = $view_paths;

        // 逐個 view 檔檢查心跳時間
        foreach ($all_viewer_paths as $view_path) {
            $view_stat = @stat($view_path);

            // 判定：stat 失敗 → 跳過
            if ($view_stat === false) {
                continue;
            }

            $idle_seconds = $now_time - (int)$view_stat["mtime"];

            // 判定：未逾時 → 保留
            if ($idle_seconds <= $expired_seconds) {
                continue;
            }

            $removed = @unlink($view_path);

            // 判定：刪除成功 → 計數
            if ($removed === true) {
                $removed_count = $removed_count + 1;
            }
        }

        // 記錄本次清掃時間（marker mtime＝上次清掃；下次據此冷卻）
        @file_put_contents($sweep_marker, date("Y-m-d H:i:s"), LOCK_EX);

        return $removed_count;
    }

    // 清舊歸檔訊息目錄＋舊 provider 備份檔（自行 60 秒節流——零外部狀態）
    public function sweep_old_archive_and_backup_files()
    {
        $stream_dir   = sc_get_config("hkai.stream_dir");
        $sweep_marker = $stream_dir . "/.last_storage_sweep";
        $now_time     = time();

        // 節流：marker mtime 未夠 60 秒 → 今次唔掃
        if (is_file($sweep_marker) === true) {
            $marker_age = $now_time - (int)@filemtime($sweep_marker);

            // 判定：冷卻期內 → 跳過
            if ($marker_age < 60) {
                return 0;
            }
        }

        // 已歸檔訊息目錄（逾期限者由 archive 自己判斷）
        sc_ai_chats()->archive()->sweep_old_archived_messages();

        // provider／benchmark model_store 內舊備份檔
        $dir_list = [];
        $dir_list[] = sc_get_config("hkai.providers_model_store_dir");
        $dir_list[] = sc_get_config("hkai.benchmark_model_store_dir");

        sc_ai_providers()->repo()->sweep_old_backup_files($dir_list);

        // 記錄本次清掃時間（marker mtime＝上次清掃）
        @file_put_contents($sweep_marker, date("Y-m-d H:i:s"), LOCK_EX);

        return 0;
    }

    // 收尾終態輪詢工作（3 秒節流＋flock 互斥；失敗 5 次即跳過——唔會卡死）
    // 上游：sse_state 每 tick（開線第一 tick 強制掃一次）
    // 下游：archive()->finalize_poll_job()（回填→DB→歸檔）＋ queue 派工
    public function sweep_terminal_poll_jobs($force_run = false)
    {
        $stream_dir      = sc_get_config("hkai.stream_dir");
        $sweep_marker    = $stream_dir . "/.last_finalize_sweep";
        $lock_path       = $stream_dir . "/.finalize.lock";
        $now_time        = time();
        $finalized_count = 0;

        // 節流：非強制、marker 未夠 3 秒 → 跳過
        if ($force_run !== true) {
            // 判定：marker 存在 → 計齡
            if (is_file($sweep_marker) === true) {
                $marker_age = $now_time - (int)filemtime($sweep_marker);

                // 判定：冷卻期內 → 跳過
                if ($marker_age < 3) {
                    return 0;
                }
            }
        }

        $lock_handle = fopen($lock_path, "cb");

        // 判定：開唔到鎖檔 → 跳過（唔影響主線）
        if ($lock_handle === false) {
            return 0;
        }

        // 判定：拎唔到鎖（另一個頁面做緊）→ 即走，下一 tick 再試
        if (flock($lock_handle, LOCK_EX | LOCK_NB) === false) {
            fclose($lock_handle);

            return 0;
        }

        try {
            $meta_files = glob(sc_get_config("hkai.messages_polling_dir") . "/*/*/*/meta.json");

            // 判定：glob 失敗 → 用空陣列
            if (is_array($meta_files) === false) {
                $meta_files = [];
            }

            foreach ($meta_files as $meta_file_item) {
                $finalized_count = $finalized_count + $this->finalize_terminal_job_file($meta_file_item);
            }
        } finally {
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
        }

        // 記錄本次掃描時間（marker mtime＝上次掃描）
        file_put_contents($sweep_marker, date("Y-m-d H:i:s"), LOCK_EX);

        return $finalized_count;
    }

    // 單條收尾（終態才做；失敗計次，5 次後跳過——防無限重試）
    private function finalize_terminal_job_file($meta_file_path)
    {
        $job_dir    = dirname($meta_file_path);
        $fail_path  = $job_dir . "/.finalize_fail";
        $fail_count = 0;

        // 判定：有失敗標記 → 讀次數
        if (is_file($fail_path) === true) {
            $fail_count = (int)file_get_contents($fail_path);
        }

        // 判定：失敗已達 5 次 → 跳過（等人工處理或下次開頁）
        if ($fail_count >= 5) {
            return 0;
        }

        // 判定：meta 讀唔到 → 跳過
        if (is_file($meta_file_path) === false) {
            return 0;
        }

        $meta_data = json_decode((string)file_get_contents($meta_file_path), true);

        // 判定：meta 內容壞 → 跳過
        if (is_array($meta_data) === false) {
            return 0;
        }

        $chat_id    = (int)($meta_data["chat_id"] ?? 0);
        $message_id = (int)($meta_data["message_id"] ?? 0);

        // 判定：缺 id → 跳過（唔可以收尾）
        if ($chat_id < 1 || $message_id < 1) {
            return 0;
        }

        // 狀態一律以 msg_status.txt 最後一行為準
        $status = (string)sc_ai_chats()->poll()->file()->get_poll_status($chat_id, $message_id);

        // 判定：未到終態 → 唔郁
        if (sc_ai_chats()->fsm()->is_terminal_status($status) === false) {
            return 0;
        }

        // 判定：目錄已被另一頁搬走 → 跳過
        if (is_dir($job_dir) === false) {
            return 0;
        }

        try {
            sc_ai_chats()->archive()->finalize_poll_job($chat_id, $message_id, $status);
        } catch (\Throwable $finalize_error) {
            file_put_contents($fail_path, (string)($fail_count + 1), LOCK_EX);

            hkai()->runtime()->logger()->error("finalize failed chat_id=" . $chat_id . " message_id=" . $message_id . " error=" . $finalize_error->getMessage());

            return 0;
        }

        // 收尾後即刻推下一條排隊（唔靠前端 poll）
        $project_slug = (string)($meta_data["project_slug"] ?? "");
        $chat_sid     = (string)($meta_data["chat_sid"] ?? "");

        // 判定：有身份 → 推帶＋派工
        if ($project_slug !== "" && $chat_sid !== "") {
            $this->dispatch_next_queued_job($project_slug, $chat_sid);
        }

        return 1;
    }

    // 推下一條排隊（失敗只記 log——唔影響收尾結果）
    private function dispatch_next_queued_job($project_slug, $chat_sid)
    {
        $queue_items = sc_ai_chats()->queue()->get_queue_items($project_slug, $chat_sid);

        // 判定：隊列空 → 冇嘢要推（正常情況，唔記錯誤）
        if (count($queue_items) < 1) {
            return;
        }

        try {
            $shift_result = sc_ai_chats()->queue()->dequeue_prompt_message_to_poll($project_slug, $chat_sid);

            // 判定：今次真係前移 → 即刻派工
            if ($shift_result["dispatched"] === true) {
                sc_ai_chats()->queue()->dispatch_polling_job();
            }
        } catch (\Throwable $dequeue_error) {
            hkai()->runtime()->logger()->error("finalize queue dispatch failed chat_sid=" . $chat_sid . " error=" . $dequeue_error->getMessage());
        }
    }
}