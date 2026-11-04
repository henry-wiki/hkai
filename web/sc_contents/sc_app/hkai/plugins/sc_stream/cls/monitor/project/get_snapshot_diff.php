<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\project;

// == project get_snapshot_diff：project entries 一層結構比對（trait——掛入 project_Class） ==
// == 上游：monitor::get_snapshot_diff()（三合一入口） ==
// == 下游：無（純比較） ==
//
// ── 輸入：state.entries（一層，key => item）───────────────────────────────────
// {
//   "home":          {"status":"active", "lastupdate":"2026-11-01 15:52:40", "chat_count":37},
//   "proj-dzke8zm7": {"status":"active", "lastupdate":"2026-11-01 14:02:11", "chat_count":12}
// }
//
// ── 輸出（三個函數，key 一律係第一層 key）────────────────────────────────────
// added   : ["proj-dz7wxoap" => {…新 item…}]
// updated : ["home" => ["old" => {…舊 item…}, "new" => {…新 item…}]]
// deleted : ["proj-e00gg9a0" => {…舊 item…}]
//
// ── 規則 ──────────────────────────────────────────────────────────────────────
// 1. 只比第一層 item；item 內任何欄位唔同＝updated（整條 item 前後）
// 2. item 唔會再拆（唔理 item 內面有幾多欄位）
// 3. 冇變 → 空 array

trait get_snapshot_diff
{
    // 重推窗口：待輸出存貨（每份一模一樣，每次燒一份）
    private $snapshot_change_copies = [];

    // 當刻有、舊版本冇 → added
    public function get_snapshot_diff_for_added($live_entries, $view_entries)
    {
        return array_diff_key($live_entries, $view_entries);
    }

    // 兩邊都有但值不同 → updated（key => [old, new]）
    public function get_snapshot_diff_for_updated($live_entries, $view_entries)
    {
        $updated_items = [];

        // 逐個當刻 item：只理舊版本都有嘅 key
        foreach ($live_entries as $entry_key => $live_value) {
            // 判定：舊版本冇此 key → added，唔關 updated
            if (array_key_exists($entry_key, $view_entries) === false) {
                continue;
            }

            $view_value = $view_entries[$entry_key];

            // 判定：值相同 → 唔算更新
            if ($live_value === $view_value) {
                continue;
            }

            $updated_items[$entry_key] = ["old" => $view_value, "new" => $live_value];
        }

        return $updated_items;
    }

    // 舊版本有、當刻冇 → deleted
    public function get_snapshot_diff_for_deleted($live_entries, $view_entries)
    {
        return array_diff_key($view_entries, $live_entries);
    }

    // 三合一比對＋重推窗口（target 唯一入口）
    public function get_snapshot_diff($live_entries, $view_entries)
    {
        $added_items   = $this->get_snapshot_diff_for_added($live_entries, $view_entries);
        $updated_items = $this->get_snapshot_diff_for_updated($live_entries, $view_entries);
        $deleted_items = $this->get_snapshot_diff_for_deleted($live_entries, $view_entries);

        $diff_count = count($added_items) + count($updated_items) + count($deleted_items);

        $changed = intval($diff_count > 0);

        $snapshot_change = [];
        $snapshot_change["changed"] = $changed;
        $snapshot_change["added"]   = $added_items;
        $snapshot_change["updated"] = $updated_items;
        $snapshot_change["deleted"] = $deleted_items;

        // 判定：有變化 → 填存貨，交第一份
        if ($changed === 1) {
            // 判定：冇設定重推次數（null）→ 直接交
            if ($this->repeat_tick_count === null) {
                return $snapshot_change;
            }

            $this->snapshot_change_copies = [];

            // 逐份存貨：每次輸出燒一份
            for ($copy_index = 0; $copy_index < $this->repeat_tick_count - 1; $copy_index++) {
                $this->snapshot_change_copies[] = $snapshot_change;
            }

            return $snapshot_change;
        } else {
            // 判定：冇變化但有存貨 → 燒一份、照交舊結果
            if (count($this->snapshot_change_copies) > 0) {
                $snapshot_change = array_shift($this->snapshot_change_copies);

                return $snapshot_change;
            } else {
                // 冇變化、冇存貨 → 正常交無變化
                return $snapshot_change;
            }
        }
    }
}
