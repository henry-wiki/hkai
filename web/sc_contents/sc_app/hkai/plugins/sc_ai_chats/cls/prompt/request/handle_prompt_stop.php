<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt handle_prompt_stop：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 message/handler.php 分獨立檔；2026-09-27 歸 prompt 域（send 生命週期——取消） ==

trait handle_prompt_stop
{

    // POST prompt_stop：取消本對話全部在飛訊息（零參數——目標一律由 polling 目錄反查）
    // 唔收 message_id、唔信前端：只認對話身份（project_slug／chat_sid）
    // 上游：request/prompt/prompt_stop.php
    // 下游：_list_in_flight_message_ids／_stop_one_message
    public function handle_prompt_stop()
    {
        // 只取對話身份（目標 message_id 唔由前端嚟——一律掃目錄反查）
        $input_data = sc_request()->json();
        $input_project_slug = (string)($input_data["project_slug"] ?? "");
        $input_chat_sid     = (string)($input_data["chat_sid"] ?? "");

        // 診斷 log：收到嘅停止請求（只有對話身份）
        hkai()->runtime()->logger()->info("prompt_stop received: project_slug=" . $input_project_slug . " | chat_sid=" . $input_chat_sid);

        // 掃 polling 目錄：本對話全部未成終態嘅在飛訊息（message_id 由目錄 meta 反查）
        $in_flight_message_ids = $this->_list_in_flight_message_ids($input_project_slug, $input_chat_sid);

        // 診斷 log：反查結果（掃到幾條）
        hkai()->runtime()->logger()->info("prompt_stop resolved: count=" . count($in_flight_message_ids) . " | project_slug=" . $input_project_slug . " | chat_sid=" . $input_chat_sid);

        // 已停嘅訊息（逐條停；冇在飛＝空）
        $stopped_message_ids = [];

        // 逐條在飛：停（每條獨立；唔喺運送中＝唔計入）
        foreach ($in_flight_message_ids as $in_flight_message_id_item) {
            $stop_one_result = $this->_stop_one_message($in_flight_message_id_item);

            // 判定：成功停 → 記錄
            if ($stop_one_result === true) {
                $stopped_message_ids[] = $in_flight_message_id_item;
            }
        }

        // 回應目標 id＝首條已停（冇＝0）
        $first_stopped_message_id = 0;

        // 判定：有停到任何一條 → 取首條
        if (count($stopped_message_ids) > 0) {
            $first_stopped_message_id = (int)$stopped_message_ids[0];
        }

        // 已停旗標
        $is_stopped = (count($stopped_message_ids) > 0);

        $response_json = [];
        $response_json["ok"]           = true;
        $response_json["stopped"]      = $is_stopped;
        $response_json["message_id"]   = $first_stopped_message_id;
        $response_json["message_ids"]  = $stopped_message_ids;
        $response_json["chat_deleted"] = false;

        return sc_response()->json($response_json);
    }

    // 掃 polling 目錄反查在飛訊息（前端唔查訊息狀態——只交對話身份）
    // 上游：handle_prompt_stop
    // 下游：poll()->file()->get_poll_jobs_by_identity
    // 回傳：message_id 陣列（全部未成終態嘅）；掃唔到／身份不齊＝空陣列
    private function _list_in_flight_message_ids($project_slug, $chat_sid)
    {
        $in_flight_message_ids = [];

        // 判定：對話身份不齊 → 冇得反查
        if ($project_slug === "" || $chat_sid === "") {
            return $in_flight_message_ids;
        }

        $poll_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs_by_identity($project_slug, $chat_sid);

        // 逐條任務：只收未成終態嘅
        foreach ($poll_jobs as $poll_job_item) {
            // 判定：已成終態 → 跳過
            if ($poll_job_item["is_terminal"]) {
                continue;
            }

            $job_message_id = (int)$poll_job_item["message_id"];

            // 判定：未蓋章 → 跳過
            if ($job_message_id < 1) {
                continue;
            }

            $in_flight_message_ids[] = $job_message_id;
        }

        return $in_flight_message_ids;
    }

    // 停一條訊息（route 反查後逐條呼叫）
    // 上游：handle_prompt_stop
    // 下游：message repo（讀列）／fsm（寫狀態）／process（殺子程序）
    // 回傳：true＝已取消；false＝唔喺運送中／找唔到
    private function _stop_one_message($message_id)
    {
        // 判定：message_id 不合法 → 失敗
        if ($message_id < 1) {
            hkai()->runtime()->logger()->warn("prompt_stop invalid: message_id=" . $message_id);

            return false;
        }

        // 以 message_id 反查 chat_id（不信任前端）
        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);

        // 判定：訊息不存在 → 失敗
        if ($message_row === null) {
            hkai()->runtime()->logger()->warn("prompt_stop not found: message_id=" . $message_id);

            return false;
        }

        $chat_id   = (int)$message_row["chat_id"];
        $prompt_id = (string)($message_row["prompt_id"] ?? "");

        // 對話列（chat_sid——三層目錄身份）+ 專案 slug（路徑唯一來源）
        $chat_row     = sc_ai_chats()->chat()->get_chat_row($chat_id);
        $chat_sid     = (string)($chat_row["chat_sid"] ?? "");
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        $box_dir = "";

        // 判定：身份齊全 → 直組箱路徑（零 DB 反查）
        if ($prompt_id !== "" && $chat_sid !== "" && $project_slug !== "") {
            $box_dir = sc_ai_chats()->poll()->get_poll_dir_by_identity($project_slug, $chat_sid, $prompt_id);
        }

        // 判定：紙箱已唔喺 polling（已歸檔／已刪）→ 唔喺運送中：補終態後回
        if ($box_dir === "" || is_dir($box_dir) === false) {
            hkai()->runtime()->logger()->info("prompt_stop ignored: not in flight | message_id=" . $message_id . " | prompt_id=" . $prompt_id);

            // 判定：DB 仲係非終態 → 由歸檔尾行（冇歸檔＝failed）補回
            $message_status = (string)($message_row["message_status"] ?? "");

            if (sc_ai_chats()->fsm()->is_terminal_status($message_status) !== true) {
                $archived_status = sc_ai_chats()->archive()->file()->read_archived_message_status($chat_id, $message_id);
                $repaired_status = "failed";

                // 判定：歸檔有終態 → 用歸檔嗰個
                if ($archived_status !== "") {
                    $repaired_status = $archived_status;
                }

                sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $repaired_status);
                hkai()->runtime()->logger()->info("prompt_stop repaired: message_id=" . $message_id . " " . $message_status . " -> " . $repaired_status);
            }

            return false;
        }

        // 活動列收尾：最後一行留取消足跡（前端顯示 [cancelled]）
        // 必須搶喺狀態落地之前寫入——狀態一寫 cancelled，任何 poll 拍都可能會歸檔移走目錄
        $msg_log_file = $box_dir . "/msg_log.txt";

        // 判定：箱內有活動列 → 補足跡
        if (file_exists($msg_log_file)) {
            sc_ai_chats()->poll()->log()->append_log($chat_id, $message_id, "Cancelled by user", ["event" => "CANCELLATION_REQUESTED"]);
        }

        // 狀態：CANCELLATION_REQUESTED → cancelled（fsm 對應表；箱路徑直傳——零反查）
        sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "CANCELLATION_REQUESTED", $box_dir);

        // 圖片抽取中（箱內有 extract.pid）→ 叫 bridge 停 extractor（idempotent）
        // 判定：箱內有抽取進程 → 停（殺進程樹＋交還認領；已完成者保留）
        if (is_file($box_dir . "/extract.pid")) {
            hkai()->process()->bridge()->request("/image_extract/stop/", ["box_dir" => $box_dir], 20);
        }

        // 精準終止該訊息的子程序（只殺本訊息——唔會誤殺同對話其他訊息）
        sc_ai_chats()->process()->kill_message_children($chat_id, $message_id);

        return true;
    }

}
