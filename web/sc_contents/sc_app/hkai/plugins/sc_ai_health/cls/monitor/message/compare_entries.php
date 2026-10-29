<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\message;

// == message compare_entries：比兩份 entries，只回有變條目（trait——掛入 message_Class） ==

trait compare_entries
{
    // 比較兩份 entries：modified／deleted／added；無變 → null
    public function compare_entries($previous_entries, $current_entries)
    {
        // 判定：今次唔係 map → 唔可逐條比對
        if (is_array($current_entries) === false) {
            return null;
        }

        // 判定：舊版本唔係 map → 當空白（全部計 added）
        if (is_array($previous_entries) === false) {
            $previous_entries = [];
        }

        $entry_changes = [];
        $all_keys      = array_unique(array_merge(array_keys($previous_entries), array_keys($current_entries)));
        sort($all_keys);

        foreach ($all_keys as $entry_key) {
            $has_previous = array_key_exists($entry_key, $previous_entries);
            $has_current  = array_key_exists($entry_key, $current_entries);

            $previous_entry = $has_previous ? $previous_entries[$entry_key] : null;
            $current_entry  = $has_current ? $current_entries[$entry_key] : null;

            // 判定：兩邊都有 → 比內容（相同＝唔列）
            if ($has_previous === true && $has_current === true) {
                $previous_json = json_encode($previous_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $current_json  = json_encode($current_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                // 判定：內容相同 → 跳過
                if ($previous_json === $current_json) {
                    continue;
                }

                $entry_changes[$entry_key] = $this->build_change_entry("modified", $previous_entry, $current_entry);
            }
            // 判定：只有今次有 → added
            else if ($has_current === true) {
                $entry_changes[$entry_key] = $this->build_change_entry("added", null, $current_entry);
            }
            // 判定：只有上次有 → deleted
            else {
                $entry_changes[$entry_key] = $this->build_change_entry("deleted", $previous_entry, null);
            }
        }

        // 判定：冇任何一條變 → null
        if (count($entry_changes) < 1) {
            return null;
        }

        return $entry_changes;
    }

    // 單條變化（type＋前後內容）
    private function build_change_entry($change_type, $previous_entry, $current_entry)
    {
        $change_entry             = [];
        $change_entry["type"]     = $change_type;
        $change_entry["previous"] = $previous_entry;
        $change_entry["current"]  = $current_entry;

        return $change_entry;
    }
}
