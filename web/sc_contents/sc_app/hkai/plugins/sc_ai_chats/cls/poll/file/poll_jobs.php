<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file poll_jobs：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-09-28 三檔合併（get_poll_jobs／get_all_poll_jobs／collect_poll_jobs——同一家族：兩入口＋worker） ==

trait poll_jobs
{

    // 取得 poll jobs（指定對話——掃描該對話訊息目錄；與 poll 同源）
    public function get_poll_jobs($chat_id, $last_message_id = 0)
    {
        return sc_ai_chats()->poll()->file()->collect_poll_jobs($chat_id, $last_message_id);
    }

    // 取得全部 poll jobs（跨對話——flush／health 全掃用）
    public function get_all_poll_jobs()
    {
        return sc_ai_chats()->poll()->file()->collect_poll_jobs(null);
    }

    // 掃描訊息目錄 → jobs（$chat_id = null → 全部；get_poll_dirs 單一掃描來源）
    // $last_message_id：前端最後見到嘅訊息——目錄已歸檔但 DB 已終態時，補一條「只供渲染」job（補交終態結果）
    public function collect_poll_jobs($chat_id, $last_message_id = 0)
    {
        // 該對話訊息目錄（目錄制 messages/polling/{project_slug}/{message_id}）
        $poll_dirs = sc_ai_chats()->poll()->file()->get_poll_dirs($chat_id);

        $jobs = [];

        // 判定：有訊息目錄 → 逐個讀 meta.json
        if ($poll_dirs) {
            foreach ($poll_dirs as $poll_dir_item) {
                // 直接讀本目錄 meta.json（免再砌路徑）
                $conf_file = $poll_dir_item . "/meta.json";
                $conf_data = sc_ai_chats()->poll()->file()->read_msg_conf_file($conf_file);

                // 判定：conf 攞唔到 → 跳過
                if ($conf_data === null) {
                    continue;
                }

                $job_message_id = (int)($conf_data["message_id"] ?? 0);

                // 判定：冇 message_id → 跳過（未蓋章）
                if ($job_message_id < 1) {
                    continue;
                }

                // chat_id 由 conf 帶入（目錄名已無 chat_id——2026-10-08 目錄改 project_slug 制）
                $job_chat_id = (int)($conf_data["chat_id"] ?? 0);

                // status 與 poll 同源（message status_Class 讀取）
                $status = sc_ai_chats()->poll()->file()->get_poll_status($job_chat_id, $job_message_id);
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

        // 判定：有帶最後訊息 → 檢查目錄已冇佢（已歸檔）但 DB 已終態 → 補一條只供渲染嘅 job
        $render_only_job = $this->build_render_only_job($chat_id, $last_message_id, $chat_jobs);

        // 判定：有補 job → 加入
        if ($render_only_job !== null) {
            $chat_jobs[] = $render_only_job;
        }

        return $chat_jobs;
    }

    // -------------------------------------------------------------------------------------------------------------------------
    // 補「只供渲染」job：目錄已歸檔（掃描搵唔到）但 DB 已終態 → 令前端仍收到最後結果
    // 上游：collect_poll_jobs()（帶 last_message_id 時）
    // 下游：handle_message_poll（只砌 task；工作二／三會跳過 is_render_only）
    // 回傳：job 陣列；唔需要補 → null
    // -------------------------------------------------------------------------------------------------------------------------
    public function build_render_only_job($chat_id, $last_message_id, $chat_jobs)
    {
        $last_message_id = (int)$last_message_id;

        // 判定：冇帶最後訊息 → 唔補
        if ($last_message_id < 1) {
            return null;
        }

        // 判定：已有同 id 嘅真實 job（仲喺 polling）→ 唔補
        foreach ($chat_jobs as $job_item) {
            // 判定：同訊息 → 唔補
            if ((int)$job_item["message_id"] === $last_message_id) {
                return null;
            }
        }

        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($last_message_id);

        // 判定：DB 冇此訊息／唔屬本對話 → 唔補
        if ($message_row === null) {
            return null;
        }

        // 判定：唔屬本對話 → 唔補
        if ((int)$message_row["chat_id"] !== (int)$chat_id) {
            return null;
        }

        $message_status = (string)$message_row["message_status"];

        // 判定：非終態（worker 未完成）→ 唔補（等 polling 目錄真實 job）
        if (sc_ai_chats()->fsm()->is_terminal_status($message_status) === false) {
            return null;
        }

        $render_only_job = [];
        $render_only_job["project_id"]   = (int)$message_row["project_id"];
        $render_only_job["chat_id"]      = (int)$chat_id;
        $render_only_job["message_id"]   = $last_message_id;
        $render_only_job["status"]       = $message_status;
        $render_only_job["is_terminal"]  = true;
        $render_only_job["is_render_only"] = true;

        return $render_only_job;
    }

}
