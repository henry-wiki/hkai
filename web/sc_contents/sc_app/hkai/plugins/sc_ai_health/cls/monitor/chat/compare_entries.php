<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\chat;

// == chat compare_entries：以 CURRENT 為基礎逐欄比對（trait——掛入 chat_Class） ==
// == 輸出：{slug: [{type, desc, lastupdate, meta:{previous, current}}]}；無變 → null ==

trait compare_entries
{
    // 比較兩份 entries（current 為基礎；再補 deleted）
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

        // 第一段：以 current 為基礎——新增／逐欄改動
        foreach ($current_entries as $project_slug => $current_entry) {
            // 判定：previous 冇此 slug → 新增
            if (array_key_exists($project_slug, $previous_entries) === false) {
                $entry_changes[$project_slug][] = $this->build_added_change($current_entry);
                continue;
            }

            $previous_entry = $previous_entries[$project_slug];
            $changed_fields = $this->resolve_changed_fields($previous_entry, $current_entry);

            foreach ($changed_fields as $field_name) {
                $change_item = $this->build_modified_change($field_name, $previous_entry, $current_entry);

                // 識別欄：每條 change 都帶 project_slug（前端守衛據此判斷屬唔屬現時專案）
                $previous_project_slug = (string)($previous_entry["project_slug"] ?? "");
                $current_project_slug  = (string)($current_entry["project_slug"] ?? "");

                $change_item["meta"]["previous"]["project_slug"] = $previous_project_slug;
                $change_item["meta"]["current"]["project_slug"]  = $current_project_slug;

                $entry_changes[$project_slug][] = $change_item;
            }
        }

        // 第二段：搵 deleted——previous 有、current 冇
        foreach ($previous_entries as $project_slug => $previous_entry) {
            // 判定：current 仍有 → 唔係刪除
            if (array_key_exists($project_slug, $current_entries) === true) {
                continue;
            }

            $entry_changes[$project_slug][] = $this->build_deleted_change($previous_entry);
        }

        // 判定：冇任何一條變 → null
        if (count($entry_changes) < 1) {
            return null;
        }

        return $entry_changes;
    }

    // 找出兩個 entry 之間有變的欄位名
    private function resolve_changed_fields($previous_entry, $current_entry)
    {
        $changed_fields = [];
        $all_fields     = array_unique(array_merge(array_keys($previous_entry), array_keys($current_entry)));

        foreach ($all_fields as $field_name) {
            $previous_value = array_key_exists($field_name, $previous_entry) ? $previous_entry[$field_name] : null;
            $current_value  = array_key_exists($field_name, $current_entry) ? $current_entry[$field_name] : null;

            // 判定：值相同 → 唔算改動
            if ($previous_value === $current_value) {
                continue;
            }

            $changed_fields[] = $field_name;
        }

        return $changed_fields;
    }

    // modified：只帶該欄前後值
    private function build_modified_change($field_name, $previous_entry, $current_entry)
    {
        $previous_value = array_key_exists($field_name, $previous_entry) ? $previous_entry[$field_name] : null;
        $current_value  = array_key_exists($field_name, $current_entry) ? $current_entry[$field_name] : null;

        $meta             = [];
        $meta["previous"] = [$field_name => $previous_value];
        $meta["current"]  = [$field_name => $current_value];

        $change_desc = $field_name . " is changed";

        // 判定：status 欄 → 語義化（archived＝歸檔；其餘＝還原）
        if ($field_name === "status") {
            $change_desc = ($current_value === "archived") ? "chat is archived" : "chat is restored";
        }

        $change_item               = [];
        $change_item["type"]       = "modified";
        $change_item["desc"]       = $change_desc;
        $change_item["lastupdate"] = (string)$current_entry["lastupdate"];
        $change_item["meta"]       = $meta;

        return $change_item;
    }

    // added：整份 entry
    private function build_added_change($current_entry)
    {
        $meta             = [];
        $meta["previous"] = null;
        $meta["current"]  = $current_entry;

        $change_item               = [];
        $change_item["type"]       = "added";
        $change_item["desc"]       = "chat is added";
        $change_item["lastupdate"] = (string)$current_entry["lastupdate"];
        $change_item["meta"]       = $meta;

        return $change_item;
    }

    // deleted：整份 entry
    private function build_deleted_change($previous_entry)
    {
        $meta             = [];
        $meta["previous"] = $previous_entry;
        $meta["current"]  = null;

        $change_item               = [];
        $change_item["type"]       = "deleted";
        $change_item["desc"]       = "chat is deleted";
        $change_item["lastupdate"] = (string)$previous_entry["lastupdate"];
        $change_item["meta"]       = $meta;

        return $change_item;
    }
}
