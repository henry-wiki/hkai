<?php

// == sc_save_settings：用戶設定持久化（settings.json 唯一寫入點）
// == 上游：setup 設定頁／任何改設定嘅功能
// == 下游：sc_data_path（檔案路徑）＋ sc_read_json／sc_write_json（暫存檔 → rename 原子替換）

/**
* 儲存用戶設定（先讀現檔，深層合併後寫回 <data>/config/settings.json）
*
* @param array $settings 要寫入嘅設定（group → key 多層）
* @return bool 成功 true／非陣列 false
*/
function sc_save_settings($settings)
{
    // 判定：唔係陣列 → 拒絕
    if (is_array($settings) === false) {
        return false;
    }

    $settings_file = sc_data_path()->get_data_sub_dir("config/settings.json");
    $existing_settings = sc_read_json($settings_file);

    // 判定：缺檔／壞檔（null）→ 以空表為底
    if ($existing_settings === null) {
        $existing_settings = array();
    }

    $merged_settings = array_replace_recursive($existing_settings, $settings);

    sc_write_json($settings_file, $merged_settings);

    return true;
}
