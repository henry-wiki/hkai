<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\chat;

// == chat get_snapshot_diff：chat entries 兩層結構比對（trait——掛入 chat_Class） ==
// == 上游：monitor::get_snapshot_diff()（三合一入口） ==
// == 下游：無（純比較——直接比原結構，唔 flatten、唔改形） ==
//
// ── 輸入：state.entries（兩層，project_slug → chat_sid → 欄位）────────────────
// {
//   "proj-dzke8zm7": {
//       "01a1966209…": {"status":"normal",   "lastupdate":"2026-11-02 02:45:51", "msg_count":15},
//       "01a1990000…": {"status":"archived", "lastupdate":"2026-11-02 14:20:01", "msg_count":1}
//   },
//   "home": {
//       "01a19582da…": {"status":"normal",   "lastupdate":"2026-11-01 15:52:40", "msg_count":16}
//   }
// }
//
// ── 輸出（同輸入同形——project_slug → chat_sid）────────────────────────────────
// added   : {"proj-dzke8zm7": {"01a1990000…": {…新 item…}}}
// updated : {"proj-dzke8zm7": {"01a1966209…": ["old" => {…舊 item…}, "new" => {…新 item…}]}}
// deleted : {"home": {"01a19582da…": {…舊 item…}}}
//
// ── 規則 ──────────────────────────────────────────────────────────────────────
// 1. 直接比原兩層結構：project_slug → chat_sid → item
// 2. item 內任何欄位唔同＝updated（整條 item 前後）
// 3. 同一 project 下新增／刪除對話，都一定捉到（因為逐 chat_sid 比）
// 4. 冇變 → 空 array

trait get_snapshot_diff
{
    // 重推窗口：待輸出存貨（每份一模一樣，每次燒一份）
    private $snapshot_change_copies = [];

    // 當刻有、舊版本冇 → added（同輸入同形：slug => sid => item）
    public function get_snapshot_diff_for_added($live_entries, $view_entries)
    {
        $added_items = [];

        // 逐個 project 比對
        foreach ($live_entries as $project_slug => $live_chats) {
            $view_chats = $view_entries[$project_slug] ?? [];

            // 逐個對話比對
            foreach ($live_chats as $chat_sid => $live_chat) {
                // 判定：舊版本已有此對話 → 唔係新增
                if (array_key_exists($chat_sid, $view_chats) === true) {
                    continue;
                }

                $added_items[$project_slug][$chat_sid] = $live_chat;
            }
        }

        return $added_items;
    }

    // 兩邊都有但值不同 → updated（同輸入同形：slug => sid => [old, new]）
    public function get_snapshot_diff_for_updated($live_entries, $view_entries)
    {
        $updated_items = [];

        // 逐個 project 比對
        foreach ($live_entries as $project_slug => $live_chats) {
            $view_chats = $view_entries[$project_slug] ?? [];

            // 逐個對話比對
            foreach ($live_chats as $chat_sid => $live_chat) {
                // 判定：舊版本冇此對話 → added，唔關 updated
                if (array_key_exists($chat_sid, $view_chats) === false) {
                    continue;
                }

                $view_chat = $view_chats[$chat_sid];

                // 判定：值相同 → 唔算更新
                if ($live_chat === $view_chat) {
                    continue;
                }

                $updated_items[$project_slug][$chat_sid] = ["old" => $view_chat, "new" => $live_chat];
            }
        }

        return $updated_items;
    }

    // 舊版本有、當刻冇 → deleted（同輸入同形：slug => sid => item）
    public function get_snapshot_diff_for_deleted($live_entries, $view_entries)
    {
        $deleted_items = [];

        // 逐個舊 project 比對
        foreach ($view_entries as $project_slug => $view_chats) {
            $live_chats = $live_entries[$project_slug] ?? [];

            // 逐個舊對話比對
            foreach ($view_chats as $chat_sid => $view_chat) {
                // 判定：當刻仍有此對話 → 唔係刪除
                if (array_key_exists($chat_sid, $live_chats) === true) {
                    continue;
                }

                $deleted_items[$project_slug][$chat_sid] = $view_chat;
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
