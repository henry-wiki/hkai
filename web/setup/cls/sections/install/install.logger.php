<?php

namespace sc\setup\sections;

// == HKAI setup sections install log：安裝清單檔收發（trait install_logger——掛入 install_Class；檔＝本格清單記錄）
// == 職責：get_install_list()＝讀 installation.json 快照（安裝照佢跑）；get_install_result()＝讀單項安裝結果；get_install_step_state()＝結果轉步狀態（init／done／fail＋詳情）；save_installation_log()＝寫清單快照；finish_setup_process()＝收尾過程（加完成時間→改名 setup_done.json）ation_log()＝寫清單快照（進安裝時——每行 id／格／label／value＋現有結果／數）
// == 載入：install/install.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

trait install_logger
{
    // 讀安裝清單（installation.json——進安裝時嘅清單快照；安裝執行＝照佢跑〔section_id＋項名搵單元〕）
    public function get_install_list()
    {
        $state_dir        = sc_get_config("setup.state_dir");
        $installation_log = $state_dir . "/installation.json";

        return json_decode(file_get_contents($installation_log), true);
    }

    // 共用：取安裝結果記錄（sections.install.<格>.<項名>——冇記錄＝null）
    public function get_install_result($install_section_id, $check_item_code)
    {
        $process_data = setup()->state()->read_setup_process();

        // 判定：有記錄 → 回記錄
        if (isset($process_data["sections"]["install"][$install_section_id][$check_item_code])) {
            return $process_data["sections"]["install"][$install_section_id][$check_item_code];
        }

        return null;
    }

    // 取安裝步狀態（讀結果記錄——init／done／fail＋詳情；顯示側單一來源）
    public function get_install_step_state($install_section_id, $check_item_code)
    {
        $install_result = $this->get_install_result($install_section_id, $check_item_code);

        // 判定：未安裝過（冇記錄）→ init
        if ($install_result === null) {
            return ["status" => "init", "detail" => ""];
        }

        // 判定：記錄＝成功 → done（detail 照記錄）
        if ($install_result["ok"] === true) {
            return ["status" => "done", "detail" => $install_result["detail"]];
        }

        return ["status" => "fail", "detail" => $install_result["detail"]];
    }

    // 儲安裝清單（installation.json——清單快照：每行 id／格／label／value＋現有結果；進安裝時寫）
    private function save_installation_log($install_steps)
    {
        $state_dir        = sc_get_config("setup.state_dir");
        $installation_log = $state_dir . "/installation.json";

        // 判定：state 目錄唔存在 → 先建立
        if (!is_dir($state_dir)) {
            mkdir($state_dir, 0777, true);
        }

        $installation_data = [];
        $installation_data["built_at"]   = date("Y-m-d H:i:s");
        $installation_data["step_count"] = count($install_steps);
        $installation_data["pass_count"] = 0;
        $installation_data["fail_count"] = 0;
        $installation_data["steps"]      = [];

        // 逐行（行＝所見清單——結果由記錄即時重報）
        foreach ($install_steps as $install_step_item) {
            $installation_step_entry = [];

            $installation_step_entry["section_id"]      = $install_step_item["section_id"];
            $installation_step_entry["check_item_code"] = $install_step_item["check_item_code"];
            $installation_step_entry["label"]           = $install_step_item["label"];
            $installation_step_entry["value"]           = $install_step_item["value"];
            $installation_step_entry["result"]          = $this->get_install_result($install_step_item["section_id"], $install_step_item["check_item_code"]);

            $installation_data["steps"][] = $installation_step_entry;
        }

        $installation_counts = $this->count_installation_results($installation_data["steps"]);

        $installation_data["pass_count"] = $installation_counts["pass_count"];
        $installation_data["fail_count"] = $installation_counts["fail_count"];

        $installation_json = json_encode($installation_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($installation_log, $installation_json, LOCK_EX);
    }

    // 收尾過程（加完成時間 → 改名 setup_done.json——完成檔＝本精靈唔再自動出現）
    public function finish_setup_process()
    {
        $setup_done    = sc_get_config("setup.setup_done");
        $state_dir     = sc_get_config("setup.state_dir");
        $setup_process = sc_get_config("setup.setup_process");

        // 判定：完成檔已存在 → 唔動（防覆寫——重入安全）
        if (is_file($setup_done)) {
            return;
        }

        // 判定：state 目錄唔存在 → 先建立
        if (!is_dir($state_dir)) {
            mkdir($state_dir, 0777, true);
        }

        $process_data = setup()->state()->read_setup_process();

        $process_data["done_at"] = date("Y-m-d H:i:s");

        $process_json = json_encode($process_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($setup_process, $process_json);

        // 完成＝改名（setup_process.json → setup_done.json——過程記錄即完成檔）
        rename($setup_process, $setup_done);
    }
}
