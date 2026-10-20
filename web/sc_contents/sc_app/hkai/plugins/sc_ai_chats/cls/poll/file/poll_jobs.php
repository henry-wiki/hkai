<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file poll_jobs：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-09-28 三檔合併（get_poll_jobs／get_all_poll_jobs／collect_poll_jobs——同一家族：兩入口＋worker） ==

trait poll_jobs
{

    // 取得 poll jobs（指定對話——掃描該對話訊息目錄；與 poll 同源）
    public function get_poll_jobs($chat_id)
    {
        return $this->collect_poll_jobs($chat_id);
    }

    // 取得全部 poll jobs（跨對話——flush／health 全掃用）
    public function get_all_poll_jobs()
    {
        return $this->collect_poll_jobs(null);
    }

    // 掃描訊息目錄 → jobs（$chat_id = null → 全部；get_poll_dirs 單一掃描來源）
    private function collect_poll_jobs($chat_id)
    {
        // 該對話訊息目錄（目錄制 messages/polling/{project_slug}/{message_id}）
        $poll_dirs = $this->get_poll_dirs($chat_id);

        $jobs = [];

        // 判定：有訊息目錄 → 逐個讀 msg_conf.json
        if ($poll_dirs) {
            foreach ($poll_dirs as $poll_dir_item) {
                // 目錄制唯一格式 {project_slug}/{message_id} → 本層＝message_id、上層＝project_slug
                $job_project_slug = basename(dirname($poll_dir_item));
                $job_message_id   = (int)basename($poll_dir_item);

                // 讀 msg_conf（唯一入口＝file 單元 get_msg_conf_data_by_slug；缺失／唔合法回 null）
                $conf_data = $this->get_msg_conf_data_by_slug($job_project_slug, $job_message_id);

                // 判定：conf 攞唔到 → 跳過
                if ($conf_data === null) {
                    continue;
                }

                // chat_id 由 conf 帶入（目錄名已無 chat_id——2026-10-08 目錄改 project_slug 制）
                $job_chat_id = (int)($conf_data["chat_id"] ?? 0);

                // status 與 poll 同源（message status_Class 讀取）
                $status = $this->get_poll_status($job_chat_id, $job_message_id);
                $jobs[] = [
                    "project_id" => (int)($conf_data["project_id"] ?? 0),
                    "chat_id"    => $job_chat_id,
                    "message_id" => $job_message_id,
                    "status"      => $status,
                    "is_terminal" => sc_ai_chats()->fsm()->is_terminal_status($status),
                ];
            }
        }

        // 判定：全掃模式（chat_id null）→ 直接回全部
        if ($chat_id === null) {
            return $jobs;
        }

        // 指定對話 → 只留本對話任務（目錄係 project 制、多對話共用——對話獨立把關）
        $chat_jobs = [];

        // 逐個任務按 chat_id 篩選
        foreach ($jobs as $job_item) {
            // 判定：任務屬本對話 → 收入
            if ($job_item["chat_id"] === (int)$chat_id) {
                $chat_jobs[] = $job_item;
            }
        }

        return $chat_jobs;
    }

}
