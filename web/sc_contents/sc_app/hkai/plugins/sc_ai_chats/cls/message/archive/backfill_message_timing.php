<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\archive;

// == sc_ai_chats message backfill_message_timing：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/archive.php 分獨立檔（one file one function） ==

trait backfill_message_timing
{

    //---------------------------------------------------------
    // 1. 由 msg_log 攞首末時間＋統計
    // 2. 寫返入 messages（一定要喺搬檔前）
    //---------------------------------------------------------
    public function backfill_message_timing($chat_id, $message_id)
    {
        $started_at  = "";
        $finished_at = "";
        
        // meta 收集（與時間同一趟掃描）
        $official_stats = "";
        $response_id    = "";
        $finish_reason  = "";
        $retry_count    = 0;
        $error_count    = 0;
        
        // msg_log：逐行 JSONL——首行取 started_at、末行取 finished_at，並收集官方統計與事件計數
        $log_path = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "log");
        
        // 判定：log 檔存在 → 逐行掃描（唔存在／空檔＝零行——無害）
        if (
            $log_path !== null
            && is_file($log_path)
        ) {
            $log_lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // 逐行掃描（首行時間只取一次；末行時間持續覆蓋到最後）
            foreach ($log_lines as $line_index => $log_line_item) {
                $log_event = json_decode($log_line_item, true);
                
                // 判定：無法解析 → 跳過本行
                if (!is_array($log_event)) {
                    continue;
                }
                
                // 判定：首行 → 取 started_at
                if (
                    $line_index === 0
                    && isset($log_event["datetime"])
                ) {
                    $started_at = $log_event["datetime"];
                }
                // 判定：本行有時間 → 持續覆蓋 finished_at（掃完即為最後一行時間）
                if (isset($log_event["datetime"])) {
                    $finished_at = $log_event["datetime"];
                }
                
                $event_name = isset($log_event["event"]) ? $log_event["event"] : "";
                
                // 判定：官方結束統計（aider 自報 token／cost）→ 取原文
                if ($event_name === "TOOL_STATS_RECORDED") {
                    $official_stats = $log_event["message"];
                }
                // 判定：連線建立 → 取 response_id
                if (
                    $event_name === "CONNECTION_ESTABLISHED"
                    && isset($log_event["metadata"]["response_id"])
                ) {
                    $response_id = $log_event["metadata"]["response_id"];
                }
                // 判定：回應完成 → 取 finish_reason
                if (
                    $event_name === "RESPONSE_COMPLETED"
                    && isset($log_event["metadata"]["finish_reason"])
                ) {
                    $finish_reason = $log_event["metadata"]["finish_reason"];
                }
                // 判定：摘要重試 → 累計重試次數
                if ($event_name === "SUMMARY_RETRY_TRIGGERED") {
                    $retry_count++;
                }
                // 判定：失敗事件 → 累計錯誤次數
                if ($event_name === "FAILURE_RECORDED") {
                    $error_count++;
                }
            }
        }
        
        // msg_conf：取執行參數（reasoning_effort／request_timeout）
        $reasoning_effort = "";
        $request_timeout  = 0;
        $conf_json = sc_ai_chats()->poll()->file()->get_msg_conf_data($chat_id, $message_id);

        // 判定：conf 可讀 → 取值
        if ($conf_json !== null) {
            $reasoning_effort = isset($conf_json["reasoning_effort"]) ? $conf_json["reasoning_effort"] : "";
            $request_timeout  = isset($conf_json["request_timeout"]) ? $conf_json["request_timeout"] : 0;
        }
        
        // meta JSON（未來新增欄位一律往這裡加）
        $message_meta = [];
        $message_meta["official_stats"]   = $official_stats;
        $message_meta["response_id"]      = $response_id;
        $message_meta["finish_reason"]    = $finish_reason;
        $message_meta["retry_count"]      = $retry_count;
        $message_meta["error_count"]      = $error_count;
        $message_meta["reasoning_effort"] = $reasoning_effort;
        $message_meta["request_timeout"]  = $request_timeout;
        
        // 精確 tokens（取最後一筆 usage＝主回覆——供 chat_{id}_meta.json 統計）
        $token_sums = sc_ai_chats()->poll()->log()->get_token_sums_from_stream_log($chat_id, $message_id);
        $message_meta["tokens_sent"]     = $token_sums["sent"];
        $message_meta["tokens_received"] = $token_sums["received"];
        
        // 耗時（秒——首末時間齊備才算）
        $elapsed_sec = 0;
        // 判定：首末時間都有值 → 計算耗時
        if (
            $started_at !== ""
            && $finished_at !== ""
        ) {
            $elapsed_sec = strtotime($finished_at) - strtotime($started_at);
        }
        
        // 寫回 messages（時間與 meta 只在終態填一次——message_repo_update）
        $message_timing = [];
        $message_timing["started_at"]  = $started_at;
        $message_timing["finished_at"] = $finished_at;
        $message_timing["elapsed_sec"] = $elapsed_sec;
        $message_timing["meta"]        = $message_meta;
        sc_ai_chats()->message()->repo_update()->update_db_message_timing($message_id, $message_timing);
    }

}
