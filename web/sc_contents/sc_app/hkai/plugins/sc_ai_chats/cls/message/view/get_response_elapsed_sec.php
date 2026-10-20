<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message view get_response_elapsed_sec：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-10-06 新設：段用時（thinking／answer／summarize 各自——計時器單一來源） ==

trait get_response_elapsed_sec
{
    // 段用時秒數（response_started_at → response_finished_at；未完結＝計到當下；無起點＝null——唔顯示）
    public function get_response_elapsed_sec($response_row)
    {
        $response_started_at  = (string)($response_row["response_started_at"] ?? "");
        $response_finished_at = (string)($response_row["response_finished_at"] ?? "");

        // 判定：無起點（未開始／無 timing）→ null
        if ($response_started_at === "") {
            return null;
        }

        $response_started_ts = (int)strtotime($response_started_at);

        // 判定：已完結 → 凍結值（finished − started）
        if ($response_finished_at !== "") {
            $response_finished_ts = (int)strtotime($response_finished_at);

            return max(0, $response_finished_ts - $response_started_ts);
        }

        // 未完結 → 計到當下（前端每秒遞增、每輪以新基準校正）
        return max(0, time() - $response_started_ts);
    }
}
