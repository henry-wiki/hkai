<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll revive_dead_worker_messages：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：把「worker 中途死亡」而被打成 failed 嘅歸檔訊息搬返 polling（只搬＋改狀態） ==
// == 上游：queue resume_polling_jobs（每對話一次——清洗／重派由該迴圈接手） ==
// == 下游：message set_message_meta_status（meta 轉 polling） ==

trait revive_dead_worker_messages
{
    // 掃歸檔區內「worker 死」造成嘅 failed 訊息 → 搬返 polling（回實際搬回數）
    public function revive_dead_worker_messages($project_slug, $chat_sid)
    {
        $revived_count = 0;

        $archive_chat_dir = sc_get_config("hkai.messages_archive_dir") . "/" . $project_slug . "/" . $chat_sid;
        $polling_chat_dir = sc_get_config("hkai.messages_polling_dir") . "/" . $project_slug . "/" . $chat_sid;

        $message_dirs = glob($archive_chat_dir . "/*", GLOB_ONLYDIR);

        // 判定：冇歸檔訊息 → 0
        if ($message_dirs === false) {
            return 0;
        }

        // 逐個歸檔箱：只搬「worker 中途死亡」嘅一條
        foreach ($message_dirs as $message_dir_item) {
            $meta_file = $message_dir_item . "/meta.json";

            // 判定：冇 meta → 跳過
            if (is_file($meta_file) === false) {
                continue;
            }

            $meta_data = json_decode(file_get_contents($meta_file), true);

            // 判定：meta 壞 → 跳過
            if (is_array($meta_data) === false) {
                continue;
            }

            $log_file = $message_dir_item . "/msg_log.txt";

            // 判定：冇 log → 跳過（無從確認死因）
            if (is_file($log_file) === false) {
                continue;
            }

            $log_text = file_get_contents($log_file);

            // 判定：log 讀唔到 → 跳過
            if ($log_text === false) {
                continue;
            }

            // 判定：log 冇「worker 死亡」足跡（daemon 判死／健康鏈自動了結）→ 唔係本流程對象
            if ($this->archived_message_has_dead_worker($log_text) === false) {
                continue;
            }

            $status_file = $message_dir_item . "/msg_status.txt";

            // 判定：冇狀態檔 → 跳過
            if (is_file($status_file) === false) {
                continue;
            }

            $status_text = file_get_contents($status_file);

            // 判定：狀態讀唔到 → 跳過
            if ($status_text === false) {
                continue;
            }

            $status_lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $status_text));
            $status_lines = array_values(array_filter($status_lines, function ($status_line_item) {
                return trim($status_line_item) !== "";
            }));

            // 判定：狀態檔全空 → 跳過
            if (count($status_lines) < 1) {
                continue;
            }

            $last_status = trim($status_lines[count($status_lines) - 1]);

            // 判定：最後狀態唔係 failed → 跳過
            if ($last_status !== "failed") {
                continue;
            }

            // 判定：polling 對話目錄未存在 → 建立
            if (is_dir($polling_chat_dir) === false) {
                mkdir($polling_chat_dir, 0777, true);
            }

            $target_dir = $polling_chat_dir . "/" . basename($message_dir_item);

            // 判定：搬唔返 polling → 跳過（維持歸檔）
            if (rename($message_dir_item, $target_dir) !== true) {
                continue;
            }

            // meta 轉 polling（清洗＋重派交 resume_polling_jobs 單一路線）
            sc_ai_chats()->message()->set_message_meta_status($target_dir, "polling");

            // 搬返 polling 目錄 → 通知監視（chat／project gate）

            $revived_count++;
        }

        return $revived_count;
    }

    // 最後一條 FAILED 係唔係「worker 死亡」（daemon 判死／健康鏈自動了結——兩種都算）
    private function archived_message_has_dead_worker($log_text)
    {
        $log_lines = explode("
", str_replace(["
", ""], "
", $log_text));

        // 由尾向上搵第一條 FAILED（＝最後一次失敗原因）
        for ($log_index = count($log_lines) - 1; $log_index >= 0; $log_index--) {
            $log_line_text = trim($log_lines[$log_index]);

            // 判定：空行 → 跳過
            if ($log_line_text === "") {
                continue;
            }

            $log_event = json_decode($log_line_text, true);

            // 判定：非 JSON → 跳過
            if (is_array($log_event) === false) {
                continue;
            }

            // 判定：未到 FAILED → 跳過
            if ((string)($log_event["event"] ?? "") !== "FAILED") {
                continue;
            }

            $failed_message = (string)($log_event["message"] ?? "");

            // 判定：daemon 判死足跡
            if (strpos($failed_message, "Warm worker died mid-job") !== false) {
                return true;
            }

            // 判定：健康鏈自動了結足跡
            if (strpos($failed_message, "Auto-finalized: worker process not running") !== false) {
                return true;
            }

            return false;
        }

        return false;
    }
}
