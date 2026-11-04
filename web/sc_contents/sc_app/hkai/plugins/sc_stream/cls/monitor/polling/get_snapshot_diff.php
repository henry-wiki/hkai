<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\polling;

// == polling get_snapshot_diff：polling entries 比較（trait——掛入 polling_Class） ==
// == 上游：monitor::get_snapshot_diff()（三合一入口） ==
// == 下游：無（重推窗口喺本檔） ==
//
// polling 專屬：比對＋重推窗口全部喺本檔

trait get_snapshot_diff
{
    // 重推窗口：待輸出存貨（每份一模一樣，每次燒一份）
    private $snapshot_change_copies = [];

    // 當刻有、本頁冇 → added
    public function get_snapshot_diff_for_added($live_entries, $view_entries)
    {
        $added_items = [];

        // 逐個項目比對
        foreach ($live_entries as $entry_key => $live_item) {
            // 判定：本頁已有此項目 → 唔係新增
            if (array_key_exists($entry_key, $view_entries) === true) {
                continue;
            }

            $added_items[$entry_key] = $live_item;
        }

        return $added_items;
    }

    // 兩邊都有但值不同 → updated
    public function get_snapshot_diff_for_updated($live_entries, $view_entries)
    {
        $updated_items = [];

        // 逐個項目比對
        foreach ($live_entries as $entry_key => $live_item) {
            // 判定：本頁冇此項目 → added，唔關 updated
            if (array_key_exists($entry_key, $view_entries) === false) {
                continue;
            }

            $view_item = $view_entries[$entry_key];

            // 判定：值相同 → 唔算更新
            if ($live_item === $view_item) {
                continue;
            }

            $updated_items[$entry_key] = ["old" => $view_item, "new" => $live_item];
        }

        return $updated_items;
    }

    // 本頁有、當刻冇 → deleted
    public function get_snapshot_diff_for_deleted($live_entries, $view_entries)
    {
        $deleted_items = [];

        // 逐個項目比對
        foreach ($view_entries as $entry_key => $view_item) {
            // 判定：當刻仍有此項目 → 唔係刪除
            if (array_key_exists($entry_key, $live_entries) === true) {
                continue;
            }

            $deleted_items[$entry_key] = $view_item;
        }

        return $deleted_items;
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
