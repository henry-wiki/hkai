<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\chat;

// == chat get_snapshot_diff：chat entries 兩層結構比對（trait——掛入 chat_Class） ==
// == 上游：monitor::get_snapshot_diff_for_added/updated/deleted() ==
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
}
