<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\prompt;

// == prompt get_snapshot_diff：比對兩份草稿 entries（trait——掛入 prompt_Class） ==
// == 輸入：{project_slug: {chat_sid: {lastupdate, draft}}} × 2（當刻／本頁已見） ==
// == 輸出：added／updated／deleted（同輸入同形：slug => sid => item） ==

trait get_snapshot_diff
{
    // 當刻有、本頁冇 → added
    public function get_snapshot_diff_for_added($live_entries, $view_entries)
    {
        $added_items = [];

        // 逐個專案比對
        foreach ($live_entries as $project_slug => $live_drafts) {
            $view_drafts = $view_entries[$project_slug] ?? [];

            // 逐個對話比對
            foreach ($live_drafts as $chat_sid => $live_draft) {
                // 判定：本頁已有此對話 → 唔係新增
                if (array_key_exists($chat_sid, $view_drafts) === true) {
                    continue;
                }

                $added_items[$project_slug][$chat_sid] = $live_draft;
            }
        }

        return $added_items;
    }

    // 兩邊都有但值不同 → updated
    public function get_snapshot_diff_for_updated($live_entries, $view_entries)
    {
        $updated_items = [];

        // 逐個專案比對
        foreach ($live_entries as $project_slug => $live_drafts) {
            $view_drafts = $view_entries[$project_slug] ?? [];

            // 逐個對話比對
            foreach ($live_drafts as $chat_sid => $live_draft) {
                // 判定：本頁冇此對話 → added，唔關 updated
                if (array_key_exists($chat_sid, $view_drafts) === false) {
                    continue;
                }

                $view_draft = $view_drafts[$chat_sid];

                // 判定：值相同 → 唔算更新
                if ($live_draft === $view_draft) {
                    continue;
                }

                $updated_items[$project_slug][$chat_sid] = ["old" => $view_draft, "new" => $live_draft];
            }
        }

        return $updated_items;
    }

    // 本頁有、當刻冇 → deleted
    public function get_snapshot_diff_for_deleted($live_entries, $view_entries)
    {
        $deleted_items = [];

        // 逐個專案比對
        foreach ($view_entries as $project_slug => $view_drafts) {
            $live_drafts = $live_entries[$project_slug] ?? [];

            // 逐個對話比對
            foreach ($view_drafts as $chat_sid => $view_draft) {
                // 判定：當刻仍有此對話 → 唔係刪除
                if (array_key_exists($chat_sid, $live_drafts) === true) {
                    continue;
                }

                $deleted_items[$project_slug][$chat_sid] = $view_draft;
            }
        }

        return $deleted_items;
    }
}
