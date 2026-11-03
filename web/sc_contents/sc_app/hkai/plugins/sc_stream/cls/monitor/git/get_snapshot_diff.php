<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\git;

// == git get_snapshot_diff：git entries 一層結構比對（trait——掛入 git_Class） ==
// == 上游：monitor::get_snapshot_diff_for_added/updated/deleted() ==
// == 下游：無（純比較） ==
//
// ── 輸入：state.entries（一層，key => item）───────────────────────────────────
// {
//   "proj-dzke8zm7": {"is_repo":true,  "branch":"master", "detached":false, "head_sha":"a1b2c3d",
//                     "upstream":"origin/master", "ahead":1, "behind":0, "dirty":2,
//                     "lastupdate":"2026-11-02 14:20:01"},
//   "proj-dz7wxoap": {"is_repo":false, "branch":"", "detached":false, "head_sha":"",
//                     "upstream":"", "ahead":0, "behind":0, "dirty":0, "lastupdate":""}
// }
//
// ── 輸出（三個函數，key 一律係第一層 key）────────────────────────────────────
// added   : ["proj-e018i6q8" => {…新 item…}]
// updated : ["proj-dzke8zm7" => ["old" => {…舊 item…}, "new" => {…新 item…}]]
// deleted : ["proj-e00gg9a0" => {…舊 item…}]
//
// ── 規則 ──────────────────────────────────────────────────────────────────────
// 1. 只比第一層 item；item 內任何欄位唔同＝updated（整條 item 前後）
// 2. item 唔會再拆（唔理 item 內面有幾多欄位）
// 3. 冇變 → 空 array

trait get_snapshot_diff
{
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
}
