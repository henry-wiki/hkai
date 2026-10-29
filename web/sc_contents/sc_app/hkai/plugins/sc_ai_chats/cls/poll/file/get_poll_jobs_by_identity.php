<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_poll_jobs_by_identity：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 職責：按 project_slug＋chat_sid 掃出該對話嘅 polling 任務（新三層直掃；舊兩層靠 conf 過濾） ==
// == 上游：is_queue_idle／finalize_poll_jobs_by_identity；下游：read_msg_conf_file／get_poll_status ==

trait get_poll_jobs_by_identity
{
    // 回 jobs（每條：project_id／chat_id／message_id／status／is_terminal）
    public function get_poll_jobs_by_identity($project_slug, $chat_sid)
    {
        $messages_dir = sc_ai_chats()->storage()->get_messages_dir();
        $project_dir  = $messages_dir . "/" . $project_slug;

        $message_dirs = [];

        // 新結構：polling/<slug>/<chat_sid>/*（prompt_id 目錄）
        $prompt_dirs = glob($project_dir . "/" . $chat_sid . "/*", GLOB_ONLYDIR);

        // 判定：有新結構目錄 → 收入
        if ($prompt_dirs !== false) {
            $message_dirs = array_merge($message_dirs, $prompt_dirs);
        }

        // 舊結構：polling/<slug>/<message_id>（conf.chat_sid 相符才算本對話）
        $legacy_dirs = glob($project_dir . "/[0-9]*", GLOB_ONLYDIR);

        // 判定：有舊結構目錄 → 逐個過濾
        if ($legacy_dirs !== false) {
            // 逐個舊目錄：讀 conf 對 chat_sid
            foreach ($legacy_dirs as $legacy_dir_item) {
                $legacy_conf = $this->read_msg_conf_file($legacy_dir_item . "/meta.json");

                // 判定：conf 讀唔到 → 跳過
                if ($legacy_conf === null) {
                    continue;
                }

                // 判定：chat_sid 相符 → 收入
                if ((string)($legacy_conf["chat_sid"] ?? "") === $chat_sid) {
                    $message_dirs[] = $legacy_dir_item;
                }
            }
        }

        $jobs = [];

        // 逐個訊息目錄：砌 job
        foreach ($message_dirs as $message_dir_item) {
            $conf_data = $this->read_msg_conf_file($message_dir_item . "/meta.json");

            // 判定：conf 讀唔到 → 跳過
            if ($conf_data === null) {
                continue;
            }

            $job_message_id = (int)($conf_data["message_id"] ?? 0);

            // 判定：未蓋章 → 跳過
            if ($job_message_id < 1) {
                continue;
            }

            $job_chat_id = (int)($conf_data["chat_id"] ?? 0);
            $status      = $this->get_poll_status($job_chat_id, $job_message_id);

            $job_item = [];
            $job_item["project_id"]  = (int)($conf_data["project_id"] ?? 0);
            $job_item["chat_id"]     = $job_chat_id;
            $job_item["message_id"]  = $job_message_id;
            $job_item["status"]      = $status;
            $job_item["is_terminal"] = sc_ai_chats()->fsm()->is_terminal_status($status);

            $jobs[] = $job_item;
        }

        return $jobs;
    }
}
