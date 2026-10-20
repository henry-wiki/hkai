<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__project_ready.php（前置檢查「app：HKAI（專案核心）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：permission()->environment__project_ready()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__project_ready_Class
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 測試（唯一真實檢查動作——插件目錄存在？回原果）
    public function setup_test()
    {
        $plugin_hkai_dir = sc_get_config("app.plugin_hkai_dir");
        $web_root        = sc_get_config("web_root");

        // 顯示用：完整路徑（插件目錄——由 /web 起）
        $plugin_hkai_display_path = str_replace($web_root, "/web", $plugin_hkai_dir);

        // 判定：插件 hkai 存在 → 原果 pass（全路徑示位）
        if (is_dir($plugin_hkai_dir)) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => $plugin_hkai_display_path . setup()->i18n()->get("check.permission.detail_exists"), "reading" => ""];
        }

        // 其餘（唔存在）→ 原果 fail（外掛不齊——主程式起不到）
        return ["ok" => false, "status" => "fail", "hint" => "", "detail" => $plugin_hkai_display_path . setup()->i18n()->get("check.permission.detail_missing"), "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    // 安裝步驟資料（標題／行值——框架步・檢查項：冇輸入值，行值留空）
    public function get_install_step_data()
    {
        return ["label" => setup()->i18n()->get("install.step.project_ready"), "value" => ""];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["label"] = setup()->i18n()->get("check.permission.project_ready.label");

        $test_result = $this->setup_test();
        $test_status = $test_result["status"];
        $test_hint   = $test_result["hint"];
        $test_detail = $test_result["detail"];

        $check_result["status"] = $test_status;

        // 判定：原果帶 hint → 入對應格（force layout 用）
        if ($test_hint !== "") {
            $check_result["result_content"][$test_status]["hint"] = $test_hint;
        }

        // 判定：原果帶 detail → 入對應格
        if ($test_detail !== "") {
            $check_result["result_content"][$test_status]["detail"] = $test_detail;
        }

        return $check_result;
    }
}
