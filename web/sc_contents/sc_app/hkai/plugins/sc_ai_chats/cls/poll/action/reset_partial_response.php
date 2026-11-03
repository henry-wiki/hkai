<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll reset_partial_response：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：恢復前清洗——清走未完成回覆嘅半截內容（response 檔／段清單／回合清單），狀態重設 queued ==
// == 上游：queue resume_polling_jobs（重派工之前一次）；下游：poll get_poll_dir／append_msg_status_by_event ==

trait reset_partial_response
{
    // 清洗單一訊息嘅半截回覆（回 bool＝有清洗到）
    public function reset_partial_response($chat_id, $message_id)
    {
        $box_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);

        // 判定：箱唔存在（已歸檔／已刪）→ 唔清洗
        if (is_dir($box_dir) === false) {
            return false;
        }

        $response_files = glob($box_dir . "/msg_response.*.txt");

        // 判定：有 response 檔 → 逐個清空
        if ($response_files !== false) {
            // 逐個半截回覆檔清空（thinking／result／replay 一律）
            foreach ($response_files as $response_file_item) {
                file_put_contents($response_file_item, "", LOCK_EX);
            }
        }

        // 段清單（每段狀態記錄）＋回合清單（每回合記錄）→ 清空
        $poll_list_file = $box_dir . "/msg_poll_list.jsonl";
        $turn_list_file = $box_dir . "/msg_turn_list.jsonl";

        // 判定：段清單存在 → 清空
        if (is_file($poll_list_file) === true) {
            file_put_contents($poll_list_file, "", LOCK_EX);
        }

        // 判定：回合清單存在 → 清空
        if (is_file($turn_list_file) === true) {
            file_put_contents($turn_list_file, "", LOCK_EX);
        }

        // 清走舊輸出（stdout／stderr）——daemon 見到 stdout 存在會跳過初始化
        $stdout_file = $box_dir . "/msg_stdout.txt";
        $stderr_file = $box_dir . "/msg_stderr.txt";

        // 判定：有舊 stdout → 刪（daemon 以「檔案存在」判已完成，唔可以留空檔）
        if (is_file($stdout_file) === true) {
            unlink($stdout_file);
        }

        // 判定：有舊 stderr → 刪
        if (is_file($stderr_file) === true) {
            unlink($stderr_file);
        }

        // 清走舊開工憑證（claim／通知標記）——否則新 worker 會被當重複／跳過
        $claim_file  = $box_dir . "/msg_claim.json";
        $notify_file = $box_dir . "/notify.agent";

        // 判定：有舊 claim → 刪
        if (is_file($claim_file) === true) {
            unlink($claim_file);
        }

        // 判定：有舊通知標記 → 刪
        if (is_file($notify_file) === true) {
            unlink($notify_file);
        }

        // 留痕（log 保留——只追加一行，唔清）
        sc_ai_chats()->poll()->log()->append_log(
            $chat_id,
            $message_id,
            "Resume: partial response cleared",
            ["event" => "QUERY_ACCEPTED"]
        );

        // 狀態重設 queued（meta 轉 polling——dispatch 分支先會接手）
        sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "QUERY_ACCEPTED", $box_dir);

        // 重設本次回覆時間基準（Total elapsed 由恢復時刻重新計）
        sc_ai_chats()->message()->repo_update()->reset_db_message_attempt($message_id);

        return true;
    }
}
