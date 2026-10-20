<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll finalize_abnormal_jobs：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-30 新設（poll 拍異常收尾——針對：未完結但已冇人做嘢嘅 job；雙重檢查先了結） ==

trait finalize_abnormal_jobs
{

    //---------------------------------------------------------
    // 針對檢查（未終態 job——卡死候選）：
    // 1. msg_log 停滯（mtime 超門檻＝冇再寫；第一道——只作前提）
    // 2. 主人唔在（msg_claim worker_pid——process_status 冇運行；第二道·權威）
    // 兩個都中先了結（留 [err_server] 足跡＋落終態 failed）；是但一個唔中＝唔郁（正常鏈仲行緊／主人仍生——防誤殺）
    // 歸檔唔喺呢度做——交返現成鏈：前端拍嘅工作三即拍收；頁面閂咗＝健康鏈 finalize_stale_job 60s 後收
    //---------------------------------------------------------
    public function finalize_abnormal_jobs($chat_id)
    {
        // 掃本對話活躍區（messages/polling/{project_slug}/{message_id}——有 conf 嘅 dir）
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs($chat_id);

        foreach ($polling_jobs as $_polling_job_item) {
            $_message_id = (int)$_polling_job_item["message_id"];
            $_status     = $_polling_job_item["status"];

            // 判定：已完結（終態）→ 唔郁，交正常收尾流程
            if (sc_ai_chats()->fsm()->is_terminal_status($_status)) {
                continue;
            }

            // 檢查①：msg_log 停滯？（event 式檔案——回覆中途可以長靜，所以只作第一道）
            $_log_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $_message_id, "log");

            // 判定：無 log 檔（未開工）→ 未有證據 → 唔殺
            if (!is_file($_log_file)) {
                continue;
            }

            $_log_age_seconds = time() - filemtime($_log_file);

            // 判定：log 仍新鮮（停滯未夠門檻）→ 唔殺
            if ($_log_age_seconds <= $this->stale_job_seconds) {
                continue;
            }

            // 檢查②：主人（msg_claim worker_pid）仲運行？（權威——防誤殺就係靠呢道）
            $_claim_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $_message_id, "claim");
            $_claim_data = null;

            // 判定：claim 檔存在 → 讀入
            if (is_file($_claim_file)) {
                $_claim_data = json_decode(file_get_contents($_claim_file), true);
            }

            // 判定：無 claim／壞檔 → 未知主人 → 唔殺
            if (!is_array($_claim_data)) {
                continue;
            }

            $_worker_pid = (int)($_claim_data["worker_pid"] ?? 0);

            // 判定：worker pid 無效 → 唔殺
            if ($_worker_pid < 1) {
                continue;
            }

            // 判定：主人健在（pid 現查＋心跳檔認名）→ 唔殺（等佢／等正常鏈）
            if ($this->is_msg_worker_alive($_worker_pid)) {
                continue;
            }

            // 雙重檢查都中（停滯＋主人已死）→ 了結：

            // 1. 活動列留終結足跡（前端最後一行顯示 [err_server]——照 handle_prompt_stop 標準：log 行必須搶喺狀態落地之前）
            sc_ai_chats()->poll()->log()->append_log($chat_id, $_message_id, "Auto-finalized: worker process not running", ["event" => "FAILED"]);

            // 2. 落終態 failed（唔歸檔——歸檔交返現成鏈：前端拍嘅工作三即拍收；頁面閂咗＝健康鏈 60s 後收）
            sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $_message_id, "FAILED");
        }
    }

    // 主人仲生唔生（pid 現查 ＋ process_status 心跳檔認名——兩者皆在＝生；缺一＝死）
    private function is_msg_worker_alive($_worker_pid)
    {
        // 判定：pid 唔存在 → 死
        if (!hkai__core()->is_process_alive($_worker_pid)) {
            return false;
        }

        // 心跳檔認名（process_status/worker.{pid}.json——擋 pid 重用誤判）
        $_heartbeat_file = hkai__core()->get_process_status_dir() . "/worker." . (int)$_worker_pid . ".json";

        // 判定：無本系統心跳檔 → 唔係本系統 worker → 死
        if (!is_file($_heartbeat_file)) {
            return false;
        }

        return true;
    }

}
