<?php

namespace sc\setup\request;

// == request record_section：record_section 動作本體（trait——掛入 request_Class；檔＝動作）
// == 職責：POST formdata[section_id][check_item_code][value]（可多項）→ 逐項交該格 get_state_value 砌狀態值 → 原樣記入過程檔（記錄層零判斷；回 {ok:true}）

trait record_section
{
    // 過程檔記錄（切格時交走目前格嘅選擇——只寫過程檔）
    public function record_section()
    {
        // ── payload：formdata[section_id][check_item_code][value]（固定三層·可多項——逐層過）
        $posted_formdata = $_POST["formdata"];

        // 逐個 section 過（key＝section_id）
        foreach ($posted_formdata as $check_item_section_id => $check_item_section_items) {
            // 本格本體（狀態值＝格自己砌——記錄層零判斷）
            $_section_instance = setup()->get_section_instance($check_item_section_id);

            // 判定：格唔對應本體 → 跳（唔處理）
            if(!$_section_instance)
            {
                continue;
            }

            // 逐個 check item 過（key＝check_item_code）
            foreach ($check_item_section_items as $check_item_code => $check_item_data) {
                // 值（三層最底）
                $check_item_value = trim($check_item_data["value"]);

                // 判定：值空＝用戶清空（唔要呢項）→ 清走記錄（唔保留舊值）
                if ($check_item_value === "") {
                    setup()->state()->remove_setting_setup_process($check_item_section_id, $check_item_code);
                    continue;
                }

                // 狀態值＝該格自己砌（逐項 if／else——敏感項遮罩加密等加工＝格嘅事）
                $check_item_state_value = $_section_instance->get_state_value($check_item_code, $check_item_value);

                // 判定：格決定唔寫（null＝無效項／未改唔重寫）→ 跳
                if($check_item_state_value === null)
                {
                    continue;
                }

                // 記入過程檔（原樣入庫——狀態值＝格砌好嘅完整記錄）
                setup()->state()->set_setting_setup_process_v2($check_item_section_id, $check_item_code, $check_item_state_value);
            }
        }

        $this->respond_json(["ok" => true], 200);
    }
}
