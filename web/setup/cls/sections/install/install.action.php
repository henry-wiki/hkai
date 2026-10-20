<?php

namespace sc\setup\sections;

// == HKAI setup sections install action：執行／結果寫入兩步（trait install_action——掛入 install_Class；檔＝本格核心動作）
// == 職責：run_unit_install()＝格＋項名搵單元跑 setup_install（標記＝專屬動作）；set_unit_install_result()＝標準結果寫回（過程檔＋清單）；清單讀寫＝install.log.php
// == 載入：install/install.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

trait install_action
{
    // 執行單步（安裝側——標記＝專屬動作；其餘＝取單元跑 setup_install）
    public function run_unit_install($install_section_id, $check_item_code)
    {
        $install_section = setup()->sections()->install();

        $unit_instance = setup()->sections()->get_section_unit_instance($install_section_id, $check_item_code);

        return $unit_instance->setup_install();
    }

    // 數安裝結果（逐行按 result 數成功／失敗——儲清單同更新結果兩邊共用）
    private function count_installation_results($installation_steps)
    {
        $installation_pass_count = 0;
        $installation_fail_count = 0;

        // 逐行（有結果先數——未跑＝兩邊都唔計）
        foreach ($installation_steps as $installation_step_item) {
            $installation_step_result = $installation_step_item["result"];

            // 判定：未跑（冇結果）→ 唔計
            if ($installation_step_result === null) {
                continue;
            }

            // 判定：成功 → 成功數 +1；否則失敗數 +1
            if ($installation_step_result["ok"] === true) {
                $installation_pass_count = $installation_pass_count + 1;
            } else {
                $installation_fail_count = $installation_fail_count + 1;
            }
        }

        return ["pass_count" => $installation_pass_count, "fail_count" => $installation_fail_count];
    }

    // 共用：設安裝結果記錄（sections.install.<格>.<項名> ＝ {ok, result, detail, update_at}——每項真實結果；完成檔已存在＝唔寫）
    public function set_unit_install_result($install_section_id, $check_item_code, $install_step_result)
    {
        $setup_done      = sc_get_config("setup.setup_done");
        $state_dir       = sc_get_config("setup.state_dir");
        $setup_process   = sc_get_config("setup.setup_process");
        $install_section = setup()->sections()->install();

        // 判定：完成檔已存在 → 唔寫（已收尾）
        if (is_file($setup_done)) {
            return;
        }

        // 判定：state 目錄唔存在 → 先建立
        if (!is_dir($state_dir)) {
            mkdir($state_dir, 0777, true);
        }

        $process_data = setup()->state()->read_setup_process();

        // 判定：sections 未立 → 立（空）
        if (!isset($process_data["sections"])) {
            $process_data["sections"] = [];
        }

        // 判定：install 格未立 → 立（空）
        if (!isset($process_data["sections"]["install"])) {
            $process_data["sections"]["install"] = [];
        }

        $install_result_entry = [];
        $install_result_entry["ok"]        = ($install_step_result["ok"] === true);
        $install_result_entry["result"]    = $install_step_result["result"];
        $install_result_entry["detail"]    = $install_step_result["detail"];
        $install_result_entry["update_at"] = date("Y-m-d H:i:s");

        // 判定：動作帶備份檔名 → 入記錄
        if (isset($install_step_result["backup_file"])) {
            $install_result_entry["backup_file"] = $install_step_result["backup_file"];
        }

        $process_data["sections"]["install"][$install_section_id][$check_item_code] = $install_result_entry;

        $process_json = json_encode($process_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($setup_process, $process_json, LOCK_EX);

        // 同步更新安裝清單記錄（installation.json——逐項結果跟住入）
        $installation_log = $state_dir . "/installation.json";

        // 判定：清單記錄存在 → 更新對應項
        if (is_file($installation_log)) {
            $installation_data = json_decode(file_get_contents($installation_log), true);

            // 逐項搵對應（格＋項名——其餘唔動）
            foreach ($installation_data["steps"] as $installation_step_index => $installation_step_item) {
                // 判定：格同名 →
                if ($installation_step_item["section_id"] === $install_section_id) {
                    // 判定：項名同名 → 入結果
                    if ($installation_step_item["check_item_code"] === $check_item_code) {
                        $installation_data["steps"][$installation_step_index]["result"] = $install_result_entry;
                    }
                }
            }

            // 行數／成功／失敗數即時重報（同清單一致）
            $installation_counts = $install_section->count_installation_results($installation_data["steps"]);

            $installation_data["step_count"] = count($installation_data["steps"]);
            $installation_data["pass_count"] = $installation_counts["pass_count"];
            $installation_data["fail_count"] = $installation_counts["fail_count"];

            $installation_json = json_encode($installation_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            file_put_contents($installation_log, $installation_json, LOCK_EX);
        }
    }
}
