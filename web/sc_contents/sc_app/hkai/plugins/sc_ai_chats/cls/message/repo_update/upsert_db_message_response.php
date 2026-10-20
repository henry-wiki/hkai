<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update upsert_db_message_response：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 chat/repo_update.php 分獨立檔；2026-09-27 歸 message 域（寫 message_response 表——訊息級） ==
// == 2026-10-05 新制：內部先掃段（get_response_sections——poll 對照＋讀檔）再 upsert；DB 非 poll 先凍結 ==
// == 2026-10-06 終態收邊：訊息已終態＋段仲 poll（daemon 收尾後遲到寫入等）→ 按訊息終態收 completed／aborted＋補 finished ==

trait upsert_db_message_response
{
    // 逐段 upsert 進 message_response（一則訊息 × 一段 = 一列；有變先寫、DB 非 poll 凍結）
    public function upsert_db_message_response($chat_row, $_polling_job_item)
    {
        $project_id          = (int)$chat_row["project_id"];
        $chat_id             = (int)$chat_row["chat_id"];
        $message_id          = (int)$_polling_job_item["message_id"];
        $message_poll_status = (string)$_polling_job_item["status"];

        // 訊息已終態？（收邊開閘——生成中各輪為進行中，零影響）
        $message_is_terminal = sc_ai_chats()->fsm()->is_terminal_status($message_poll_status);

        // 段收邊目標（completed → completed；其餘（failed／cancelled）→ aborted——同 daemon complete_all／abort_all 對稱）
        if ($message_poll_status === "completed") {
            $closed_response_status = "completed";
        } else {
            $closed_response_status = "aborted";
        }

        $current_time = date("Y-m-d H:i:s");
        $connection = sc_db()->connection("sqlite");

        // 段落（poll 對照＋讀檔——狀態＝poll_status；error 段同一形）
        $response_sections = sc_ai_chats()->poll()->file()->get_response_sections($chat_id, $message_id, $message_poll_status);

        // 現行回應列簡版（讀取——3 欄；一次過讀，唔使逐段查）
        $response_brief_rows = sc_ai_chats()->message()->repo_select()->get_message_response_brief_rows($message_id);

        // 轉 map（key＝poll_file——判存在／凍結）
        $response_brief_rows = sc_ai_chats()->message()->normalize()->response_brief_rows($response_brief_rows);

        // 逐段（段＝poll 對照條目原文＋內容）
        foreach ($response_sections as $response_section_item) {

            $_poll_file   = $response_section_item["poll_file"];
            $_poll_status = (string)$response_section_item["poll_status"];

            // 段有效狀態（預設照原文）
            $_poll_status_effective = $_poll_status;

            // 判定：段仲係 poll（未見完成標記——daemon 收尾後遲到寫入等）
            if ($_poll_status === "poll") {
                // 判定：訊息已終態（收邊開閘——未終態＝正常生成中，照 poll）
                if ($message_is_terminal === true) {
                    $_poll_status_effective = $closed_response_status;
                }
            }

            // 現行行（key＝poll_file）
            $response_row = $response_brief_rows[$_poll_file] ?? null;

            // 判定：行已存在 → 用 DB 狀態把關
            if ($response_row !== null) {
                $response_status = $response_row["response_status"] ?? "poll";

                // 判定：DB 非 poll（已收尾）→ 跳過（唔再寫）
                if ($response_status !== "poll") {
                    continue;
                }
            }

            // 段型／索引（有 poll_file → 由檔名拆；error 段（無檔）→ 食段自帶）
            if ($_poll_file === "") {
                $response_type  = (string)$response_section_item["response_type"];
                $response_index = (int)$response_section_item["response_index"];
            } else {
                $response_type  = sc_ai_chats()->message()->normalize()->get_response_type_from_poll_file($_poll_file);
                $response_index = sc_ai_chats()->message()->normalize()->get_response_index_from_poll_file($_poll_file);
            }
            $response_content = (string)$response_section_item["response_content"];

            // 段起訖（poll 對照——Python 唯一事實）：起＝poll_start_at；已收＝poll_update_at（凍結基準）；未完結＝空
            $response_started_at  = (string)($response_section_item["poll_start_at"] ?? "");
            $response_finished_at = "";

            // 判定：段有效狀態已收（completed 或終態收邊）→ 記完成時間
            if ($_poll_status_effective !== "poll") {
                $response_finished_at = (string)($response_section_item["poll_update_at"] ?? "");
            }

            // ① 基本行 datapairs（identity＋created／status）
            $insert_datapairs = [];
            $insert_datapairs["project_id"]          = $project_id;
            $insert_datapairs["chat_id"]             = $chat_id;
            $insert_datapairs["message_id"]          = $message_id;
            $insert_datapairs["response_type"]       = $response_type;
            $insert_datapairs["response_index"]      = $response_index;
            $insert_datapairs["response_created_at"] = $current_time;
            $insert_datapairs["response_started_at"] = $response_started_at;
            $insert_datapairs["response_status"]     = $_poll_status_effective;

            // ② needle datapairs（內容／狀態／索引／時間）
            $update_datapairs = [];
            $update_datapairs["response_content"]     = $response_content;
            $update_datapairs["response_status"]      = $_poll_status_effective;
            $update_datapairs["response_index"]       = $response_index;
            $update_datapairs["response_updated_at"]  = $current_time;
            $update_datapairs["response_started_at"]  = $response_started_at;
            $update_datapairs["response_finished_at"] = $response_finished_at;

            // 交易：① 基本行 ② 查 id ③ update needle（by id）——一齊成／一齊敗
            $connection->transaction(function ($db) use ($insert_datapairs, $update_datapairs) {
                // ① 基本行（撞 (message_id, response_type, response_index) → 只 set response_updated_at）
                $db->statement(
                    "INSERT INTO message_response (project_id, chat_id, message_id, response_type, response_index, response_created_at, response_started_at, response_status)"
                    . " VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    . " ON CONFLICT(message_id, response_type, response_index) DO UPDATE SET response_updated_at = ?",
                    array_merge(array_values($insert_datapairs), [$update_datapairs["response_updated_at"]])
                );

                // ② 查返行 id（insert 後——行必在）
                $response_row = $db->selectOne(
                    "SELECT response_id FROM message_response WHERE message_id = ? AND response_type = ? AND response_index = ?",
                    [$insert_datapairs["message_id"], $insert_datapairs["response_type"], $insert_datapairs["response_index"]]
                );

                $response_id = (int)($response_row["response_id"] ?? 0);

                // ③ update needle（內容／狀態／索引／時間——by id）
                $db->statement(
                    "UPDATE message_response SET response_content = ?, response_status = ?, response_index = ?, response_updated_at = ?, response_started_at = ?, response_finished_at = ? WHERE response_id = ?",
                    array_merge(array_values($update_datapairs), [$response_id])
                );
            });
        }
    }

}
