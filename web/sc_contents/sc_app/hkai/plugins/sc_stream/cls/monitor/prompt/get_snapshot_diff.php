<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\prompt;

// == prompt get_snapshot_diff：比對兩份草稿 entries（trait——掛入 prompt_Class） ==
// == 輸入：{project_slug: {chat_sid: {lastupdate, draft}}} × 2（當刻／本頁已見） ==
// == 輸出：added／updated／deleted（同輸入同形：slug => sid => item） ==

trait get_snapshot_diff
{
    // 重推窗口：待輸出存貨（每份一模一樣，每次燒一份）
    private $snapshot_change_copies = [];

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
