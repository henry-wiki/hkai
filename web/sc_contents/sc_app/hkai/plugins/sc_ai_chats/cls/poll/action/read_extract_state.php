<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll read_extract_state：poll 域查詢（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：讀 data/_tmp/image_extract/<message_id>/（job.json＋<idx>.status.json）→ 派生整體狀態 ==
// == 上游：poll()->promote_prepared_jobs（preparing 監視）；下游：image_extractor 狀態檔（一個檔案一個真相）

trait read_extract_state
{

    //---------------------------------------------------------
    // 1. 讀 job.json（冇＝空結果——唔係所有訊息都有附件）
    // 2. 逐張讀 <idx>.status.json（缺＝pending）
    // 3. 派生整體狀態（cancelled > done > done_with_errors > running > pending）
    //---------------------------------------------------------
    public function read_extract_state($message_id)
    {
        $extract_state = [];
        $extract_state["state"] = "";
        $extract_state["done"]  = 0;
        $extract_state["total"] = 0;
        $extract_state["files"] = [];

        $job_dir  = sc_get_config("hkai.image_extract_dir") . "/" . (int)$message_id;
        $job_file = $job_dir . "/job.json";

        // 判定：冇 job 檔 → 空結果（該訊息冇附件／未派抽取）
        if (!is_file($job_file)) {
            return $extract_state;
        }

        $job_data = json_decode(@file_get_contents($job_file), true);

        // 判定：job.json 壞／唔係物件 → 空結果
        if (!is_array($job_data)) {
            return $extract_state;
        }

        $job_files = $job_data["files"] ?? [];

        // 判定：files 唔係陣列 → 空結果
        if (!is_array($job_files)) {
            return $extract_state;
        }

        $state_list = [];
        $done_count = 0;

        // 逐張讀狀態檔
        foreach ($job_files as $job_file_item) {
            $idx         = (int)($job_file_item["idx"] ?? 0);
            $path        = (string)($job_file_item["path"] ?? "");
            $status_file = $job_dir . "/" . $idx . ".status.json";
            $file_state  = "pending";
            $meta_path   = "";
            $ocr_chars   = 0;

            // 判定：狀態檔存在 → 讀內容
            if (is_file($status_file)) {
                $status_data = json_decode(@file_get_contents($status_file), true);

                // 判定：狀態檔正常 → 取值
                if (is_array($status_data)) {
                    $file_state = (string)($status_data["state"] ?? "pending");
                    $meta_path  = (string)($status_data["meta_path"] ?? "");
                    $ocr_chars  = (int)($status_data["ocr_chars"] ?? 0);
                }
            }

            $state_list[] = $file_state;

            // 判定：已完成 → 計數
            if ($file_state === "done") {
                $done_count++;
            }

            $extract_state["files"][] = [
                "idx"       => $idx,
                "state"     => $file_state,
                "path"      => $path,
                "meta_path" => $meta_path,
                "ocr_chars" => $ocr_chars,
            ];
        }

        $extract_state["total"] = count($state_list);
        $extract_state["done"]  = $done_count;
        $extract_state["state"] = sc_ai_chats()->poll()->derive_extract_state($state_list);

        return $extract_state;
    }
}
