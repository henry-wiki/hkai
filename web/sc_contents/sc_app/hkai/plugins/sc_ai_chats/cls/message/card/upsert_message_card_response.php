<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message card upsert_message_card_response：card 子域動作（trait——合成入 card_Class） ==
// == 逐段 upsert 入卡檔——同 DB 版 upsert_db_message_response 同一來源、同一三步、同一凍結規則 ==
// == 三步：① ensure row（冇＝insert、有＝用）② 取 response_id ③ update 欄位 ==

trait upsert_message_card_response
{
    // 卡版 upsert（一則訊息 × 一段 = 一 row；DB 非 poll 凍結）
    public function upsert_message_card_response($chat_row, $_polling_job_item)
    {
        $project_id          = (int)$chat_row["project_id"];
        $chat_id             = (int)$chat_row["chat_id"];
        $message_id          = (int)$_polling_job_item["message_id"];
        $message_poll_status = (string)$_polling_job_item["status"];

        // 訊息已終態？（收邊開閘——同 DB 版）
        $message_is_terminal = sc_ai_chats()->fsm()->is_terminal_status($message_poll_status);

        // 段收邊目標（completed → completed；其餘 → aborted——同 DB 版對稱）
        if ($message_poll_status === "completed") {
            $closed_response_status = "completed";
        } else {
            $closed_response_status = "aborted";
        }

        $current_time = date("Y-m-d H:i:s");

        // 段落（poll 對照＋讀檔——同 DB 版同一來源）
        $response_sections = sc_ai_chats()->poll()->file()->get_response_sections($chat_id, $message_id, $message_poll_status);

        // 卡（冇＝由 DB message 列開卡——message 1 row 先入）
        $message_card = $this->read_message_card($message_id);

        // 判定：卡未建立 → 由 DB 讀 message 列開卡
        if ($message_card === null) {
            $message_row = sc_db()->connection("sqlite")->selectOne("SELECT * FROM messages WHERE message_id = ?", [$message_id]);

            // 判定：DB 都冇此訊息 → 唔開卡
            if ($message_row === null) {
                return;
            }

            // 判定：DB 訊息已終態（finalize 已 flush 完）→ 唔再開卡（防終態後殘卡）
            if (sc_ai_chats()->fsm()->is_terminal_status($message_row["message_status"])) {
                return;
            }

            $message_card = [];
            $message_card["table"]     = "message";
            $message_card["message"]   = $message_row;
            $message_card["responses"] = [];
        }

        // 逐段（三步：ensure row → 取 id → update 欄位）
        foreach ($response_sections as $response_section_item) {
            $poll_file   = $response_section_item["poll_file"];
            $poll_status = (string)$response_section_item["poll_status"];

            // 段有效狀態（預設照原文——同 DB 版）
            $poll_status_effective = $poll_status;

            // 判定：段仲係 poll 而訊息已終態 → 按訊息終態收邊
            if ($poll_status === "poll") {
                if ($message_is_terminal === true) {
                    $poll_status_effective = $closed_response_status;
                }
            }

            // 段型／索引（有 poll_file → 由檔名拆；error 段 → 食段自帶——同 DB 版）
            if ($poll_file === "") {
                $response_type  = (string)$response_section_item["response_type"];
                $response_index = (int)$response_section_item["response_index"];
            } else {
                $response_type  = sc_ai_chats()->message()->normalize()->get_response_type_from_poll_file($poll_file);
                $response_index = sc_ai_chats()->message()->normalize()->get_response_index_from_poll_file($poll_file);
            }

            $response_content = (string)$response_section_item["response_content"];

            // 段起訖（同 DB 版：起＝poll_start_at；已收＝poll_update_at；未完結＝空）
            $response_started_at  = (string)($response_section_item["poll_start_at"] ?? "");
            $response_finished_at = "";

            // 判定：段有效狀態已收 → 記完成時間
            if ($poll_status_effective !== "poll") {
                $response_finished_at = (string)($response_section_item["poll_update_at"] ?? "");
            }

            // 現行 row 位置（key＝message_id, response_type, response_index）
            $response_row_index = $this->find_card_response_row_index($message_card["responses"], $message_id, $response_type, $response_index);

            // 判定：row 已存在 → 用卡狀態把關（非 poll＝已收尾 → 跳過，唔再寫）
            if ($response_row_index !== null) {
                // 判定：卡非 poll（已收尾）→ 跳過
                if ($message_card["responses"][$response_row_index]["response_status"] !== "poll") {
                    continue;
                }

                // ③ update 欄位（同 DB UPDATE 同一組欄位）
                $message_card["responses"][$response_row_index]["poll_file"]            = $poll_file;
                $message_card["responses"][$response_row_index]["response_content"]     = $response_content;
                $message_card["responses"][$response_row_index]["response_status"]      = $poll_status_effective;
                $message_card["responses"][$response_row_index]["response_index"]       = $response_index;
                $message_card["responses"][$response_row_index]["response_updated_at"]  = $current_time;
                $message_card["responses"][$response_row_index]["response_started_at"]  = $response_started_at;
                $message_card["responses"][$response_row_index]["response_finished_at"] = $response_finished_at;

                continue;
            }

            // ① ensure row：唔存在 → insert（response_id＝全域序號——終態 flush 帶返 DB）
            $response_id = $this->allocate_response_id();

            // 判定：發號失敗 → 跳過本段（下一輪再試）
            if ($response_id < 1) {
                continue;
            }

            // ② 新 row（欄位同名同序＝message_response 表）
            $insert_response_row = [];
            $insert_response_row["response_id"]          = $response_id;
            $insert_response_row["project_id"]           = $project_id;
            $insert_response_row["chat_id"]              = $chat_id;
            $insert_response_row["message_id"]           = $message_id;
            $insert_response_row["poll_file"]            = $poll_file;
            $insert_response_row["response_type"]        = $response_type;
            $insert_response_row["response_index"]       = $response_index;
            $insert_response_row["response_content"]     = $response_content;
            $insert_response_row["response_status"]      = $poll_status_effective;
            $insert_response_row["response_started_at"]  = $response_started_at;
            $insert_response_row["response_finished_at"] = $response_finished_at;
            $insert_response_row["response_created_at"]  = $current_time;
            $insert_response_row["response_updated_at"]  = $current_time;

            $message_card["responses"][] = $insert_response_row;
        }

        $this->write_message_card($message_id, $message_card);
    }

    // 搵 row 位置（key＝message_id, response_type, response_index；唔存在＝null）
    private function find_card_response_row_index($response_rows, $message_id, $response_type, $response_index)
    {
        // 逐行對 key
        foreach ($response_rows as $response_row_index => $response_row_item) {
            // 判定：message_id 唔同 → 跳過
            if ((int)$response_row_item["message_id"] !== (int)$message_id) {
                continue;
            }

            // 判定：型別唔同 → 跳過
            if ((string)$response_row_item["response_type"] !== (string)$response_type) {
                continue;
            }

            // 判定：索引相同 → 命中
            if ((int)$response_row_item["response_index"] === (int)$response_index) {
                return $response_row_index;
            }
        }

        return null;
    }
}
