<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\message;

// == message get_snapshot_diff：比對兩份訊息 entries（trait——掛入 message_Class） ==
// == 輸入：{message_id: {status, lastupdate}} × 2（當刻／本頁已見） ==
// == 輸出：added／updated／deleted（key 一律 message_id） ==

trait get_snapshot_diff
{
    // 當刻有、本頁冇 → added
    public function get_snapshot_diff_for_added($live_entries, $view_entries)
    {
        $added_items = [];

        // 逐條訊息比對
        foreach ($live_entries as $message_id => $live_item) {
            // 判定：本頁已有此訊息 → 唔係新增
            if (array_key_exists($message_id, $view_entries) === true) {
                continue;
            }

            $added_items[$message_id] = $live_item;
        }

        return $added_items;
    }

    // 兩邊都有但值不同 → updated
    public function get_snapshot_diff_for_updated($live_entries, $view_entries)
    {
        $updated_items = [];

        // 逐條訊息比對
        foreach ($live_entries as $message_id => $live_item) {
            // 判定：本頁冇此訊息 → added，唔關 updated
            if (array_key_exists($message_id, $view_entries) === false) {
                continue;
            }

            $view_item = $view_entries[$message_id];

            // 判定：值相同 → 唔算更新
            if ($live_item === $view_item) {
                continue;
            }

            $updated_items[$message_id] = ["old" => $view_item, "new" => $live_item];
        }

        return $updated_items;
    }

    // 本頁有、當刻冇 → deleted
    public function get_snapshot_diff_for_deleted($live_entries, $view_entries)
    {
        $deleted_items = [];

        // 逐條訊息比對
        foreach ($view_entries as $message_id => $view_item) {
            // 判定：當刻仍有此訊息 → 唔係刪除
            if (array_key_exists($message_id, $live_entries) === true) {
                continue;
            }

            $deleted_items[$message_id] = $view_item;
        }

        return $deleted_items;
    }
}
