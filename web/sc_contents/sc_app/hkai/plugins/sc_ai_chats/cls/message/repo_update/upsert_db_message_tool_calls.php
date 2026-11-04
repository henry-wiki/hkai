<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update upsert_db_message_tool_calls：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 職責：掃 msg_response.{i}.tools.jsonl（每輪一檔）→ 按 tool_call_id 合 start／done → upsert 入 message_tool_call ==
// == 上游：archive\finalize_poll_job（歸檔收尾同一位置） ==
// == 下游：sc_db()->connection("sqlite")->transaction()

trait upsert_db_message_tool_calls
{
    // 逐輪工具調用 upsert（一格 response_index 一個 transaction——滿檔一次過寫）
    public function upsert_db_message_tool_calls($chat_row, $_polling_job_item)
    {
        $project_id = (int)$chat_row["project_id"];
        $chat_id    = (int)$chat_row["chat_id"];
        $message_id = (int)$_polling_job_item["message_id"];

        $message_dir = sc_ai_chats()->poll()->get_existing_message_dir($message_id);

        // 判定：目錄唔存在 → 唔寫
        if (is_dir($message_dir) === false) {
            return 0;
        }

        $tools_files = glob($message_dir . "/msg_response.*.tools.jsonl");

        // 判定：冇工具檔 → 唔寫
        if (is_array($tools_files) === false) {
            return 0;
        }

        $written_count = 0;

        // 逐輪（一個檔一個 transaction）
        foreach ($tools_files as $tools_file_item) {
            $response_index = $this->get_tools_file_response_index($tools_file_item);
            $tool_records   = $this->collect_tool_records($tools_file_item);

            // 判定：本輪冇記錄 → 跳過
            if (count($tool_records) < 1) {
                continue;
            }

            $written_count = $written_count + $this->upsert_tool_records($project_id, $chat_id, $message_id, $response_index, $tool_records);

            // 同步寫一段 message_response（type＝response_tool——畫面渲染用）
            $this->upsert_tool_response_row($project_id, $chat_id, $message_id, $response_index, $tool_records);
        }

        return $written_count;
    }

    // 由檔名抽 response index（msg_response.{i}.tools.jsonl）
    private function get_tools_file_response_index($tools_file)
    {
        $match_result = [];

        // 判定：檔名唔合格式 → 0
        if (preg_match("/msg_response\.(\d+)\.tools\.jsonl$/", $tools_file, $match_result) !== 1) {
            return 0;
        }

        return (int)$match_result[1];
    }

    // 讀檔 → 按 tool_call_id 合 start／done（key＝tool_call_id）
    private function collect_tool_records($tools_file)
    {
        $tool_records = [];
        $file_lines   = file($tools_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 判定：讀唔到 → 空
        if (is_array($file_lines) === false) {
            return $tool_records;
        }

        // 逐行（每行一個事件）
        foreach ($file_lines as $file_line_item) {
            $record_data = json_decode($file_line_item, true);

            // 判定：解析失敗 → 跳過
            if (is_array($record_data) === false) {
                continue;
            }

            $tool_call_id = (string)($record_data["tool_call_id"] ?? "");

            // 判定：冇 id → 跳過
            if ($tool_call_id === "") {
                continue;
            }

            // 判定：首次見到 → 起格（seq 定顯示次序）
            if (isset($tool_records[$tool_call_id]) === false) {
                $tool_records[$tool_call_id] = [];
                $tool_records[$tool_call_id]["seq"]         = (int)($record_data["seq"] ?? 0);
                $tool_records[$tool_call_id]["tool_name"]   = (string)($record_data["tool"] ?? "");
                $tool_records[$tool_call_id]["input_text"]  = "";
                $tool_records[$tool_call_id]["output_text"] = "";
                $tool_records[$tool_call_id]["status"]      = "";
                $tool_records[$tool_call_id]["started_at"]  = "";
                $tool_records[$tool_call_id]["finished_at"] = "";
            }

            $record_phase    = (string)($record_data["phase"] ?? "start");
            $record_datetime = (string)($record_data["datetime"] ?? "");

            // 判定：start → 記輸入同開始時間（空值唔覆蓋已有）
            if ($record_phase === "start") {
                $record_input = (string)($record_data["input_full"] ?? "");

                // 判定：有輸入 → 覆蓋（後續 in_progress 行冇輸入，唔可以清走）
                if ($record_input !== "") {
                    $tool_records[$tool_call_id]["input_text"] = $record_input;
                }

                // 判定：未有開始時間／有狀態 → 更新
                if ($tool_records[$tool_call_id]["started_at"] === "") {
                    $tool_records[$tool_call_id]["started_at"] = $record_datetime;
                }

                if ((string)($record_data["status"] ?? "") !== "") {
                    $tool_records[$tool_call_id]["status"] = (string)$record_data["status"];
                }

                continue;
            }

            // done → 記輸出、狀態、完成時間
            $tool_records[$tool_call_id]["output_text"] = (string)($record_data["output_full"] ?? "");
            $tool_records[$tool_call_id]["status"]      = (string)($record_data["status"] ?? "");
            $tool_records[$tool_call_id]["finished_at"] = $record_datetime;
        }

        return $tool_records;
    }

    // 寫入（一個 transaction、executemany 效果；ON CONFLICT 補 done 欄位）
    private function upsert_tool_records($project_id, $chat_id, $message_id, $response_index, $tool_records)
    {
        $connection = sc_db()->connection("sqlite");
        $written_count = 0;

        $connection->transaction(function () use ($connection, $project_id, $chat_id, $message_id, $response_index, $tool_records, &$written_count) {
            $upsert_sql = "INSERT INTO message_tool_call"
                . " (tool_call_id, project_id, chat_id, message_id, response_index, seq, tool_name, input_text, output_text, status, started_at, finished_at)"
                . " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                . " ON CONFLICT(tool_call_id) DO UPDATE SET"
                . " output_text = CASE WHEN excluded.output_text <> '' THEN excluded.output_text ELSE message_tool_call.output_text END,"
                . " status      = CASE WHEN excluded.status      <> '' THEN excluded.status      ELSE message_tool_call.status END,"
                . " finished_at = CASE WHEN excluded.finished_at <> '' THEN excluded.finished_at ELSE message_tool_call.finished_at END";

            // 逐條 upsert（同一 transaction 內）
            foreach ($tool_records as $tool_call_id => $record_item) {
                $bindings = [];
                $bindings[] = $tool_call_id;
                $bindings[] = $project_id;
                $bindings[] = $chat_id;
                $bindings[] = $message_id;
                $bindings[] = $response_index;
                $bindings[] = $record_item["seq"];
                $bindings[] = $record_item["tool_name"];
                $bindings[] = $record_item["input_text"];
                $bindings[] = $record_item["output_text"];
                $bindings[] = $record_item["status"];
                $bindings[] = $record_item["started_at"];
                $bindings[] = $record_item["finished_at"];

                $connection->statement($upsert_sql, $bindings);

                $written_count = $written_count + 1;
            }
        });

        return $written_count;
    }

    // tool 段（message_response 一列：type＝response_tool；內容＝命令清單文字）
    private function upsert_tool_response_row($project_id, $chat_id, $message_id, $response_index, $tool_records)
    {
        $connection = sc_db()->connection("sqlite");

        $tool_items = [];

        // 逐條工具：砌一個元素（seq 次序；畫面一格一條）
        foreach ($tool_records as $record_item) {
            $tool_item = [];
            $tool_item["seq"]    = (int)$record_item["seq"];
            $tool_item["tool"]   = (string)$record_item["tool_name"];
            $tool_item["status"] = (string)$record_item["status"];
            $tool_item["input"]  = (string)$record_item["input_text"];
            $tool_item["output"] = (string)$record_item["output_text"];

            $tool_items[] = $tool_item;
        }

        $tool_content = json_encode($tool_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $current_time = date("Y-m-d H:i:s");

        // 起止時間（min started／max finished——工具段用時顯示用）
        $tool_started_at  = "";
        $tool_finished_at = "";

        // 逐條工具：取最早開始、最遲完成
        foreach ($tool_records as $record_item) {
            // 判定：有開始時間 → 取最早
            if ($record_item["started_at"] !== "") {
                if ($tool_started_at === "" || $record_item["started_at"] < $tool_started_at) {
                    $tool_started_at = $record_item["started_at"];
                }
            }

            // 判定：有完成時間 → 取最遲
            if ($record_item["finished_at"] !== "") {
                if ($tool_finished_at === "" || $record_item["finished_at"] > $tool_finished_at) {
                    $tool_finished_at = $record_item["finished_at"];
                }
            }
        }

        $existing_row = $connection->selectOne(
            "SELECT response_id FROM message_response WHERE message_id = ? AND response_type = 'response_tool' AND response_index = ?",
            [$message_id, $response_index]
        );

        // 判定：未有 tool 列 → 插入
        if ($existing_row === null) {
            $connection->insert(
                "INSERT INTO message_response (project_id, chat_id, message_id, poll_file, response_type, response_index, response_content, response_status, response_started_at, response_finished_at, response_created_at, response_updated_at)"
                . " VALUES (?, ?, ?, ?, 'response_tool', ?, ?, 'completed', ?, ?, ?, ?)",
                [$project_id, $chat_id, $message_id, "response." . $response_index . ".tools", $response_index, $tool_content, $tool_started_at, $tool_finished_at, $current_time, $current_time]
            );

            return;
        }

        $response_id = (int)$existing_row["response_id"];

        // 已有 → 更新內容（長度／狀態同步）
        $connection->statement(
            "UPDATE message_response SET response_content = ?, response_status = 'completed', response_started_at = ?, response_finished_at = ?, response_updated_at = ? WHERE response_id = ?",
            [$tool_content, $tool_started_at, $tool_finished_at, $current_time, $response_id]
        );
    }
}
