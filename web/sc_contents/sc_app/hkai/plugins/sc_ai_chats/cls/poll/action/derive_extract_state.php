<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll derive_extract_state：poll 域查詢（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：由逐張狀態派生整體狀態（一個檔案一個真相——唔寫 aggregate 檔） ==
// == 上游：read_extract_state；下游：無（純函數）

trait derive_extract_state
{

    //---------------------------------------------------------
    // 派生整體狀態（唔另寫 aggregate 檔——各檔案狀態為唯一真相）
    //---------------------------------------------------------
    public function derive_extract_state($state_list)
    {
        // 判定：冇任何檔案 → pending
        if (count($state_list) < 1) {
            return "pending";
        }

        $is_all_done     = true;
        $is_all_finished = true;
        $has_failed      = false;

        foreach ($state_list as $file_state) {
            // 判定：有 cancelled → cancelled（最高優先）
            if ($file_state === "cancelled") {
                return "cancelled";
            }

            // 判定：未 done → 未全部完成
            if ($file_state !== "done") {
                $is_all_done = false;
            }

            // 判定：未完結（done／failed 以外）→ 未全部收尾
            if ($file_state !== "done" && $file_state !== "failed") {
                $is_all_finished = false;
            }

            // 判定：有 failed → 記低
            if ($file_state === "failed") {
                $has_failed = true;
            }
        }

        // 判定：全部 done → done
        if ($is_all_done) {
            return "done";
        }

        // 判定：全部完結且有 failed → done_with_errors
        if ($is_all_finished && $has_failed) {
            return "done_with_errors";
        }

        // 判定：有 running → running
        if (in_array("running", $state_list, true)) {
            return "running";
        }

        return "pending";
    }
}
