<?php

namespace sc\setup\sections;

// 載入 install 格內部短名（install.func.php——setup()／域門）
require_once __DIR__ . "/install.func.php";

// 載入本格 view 側 trait（六個 view 方法——include once）
require_once __DIR__ . "/install.view.php";

// 載入本格核心動作 trait（install_action——清單／執行／結果；include once）
require_once __DIR__ . "/install.action.php";

// 載入本格清單記錄 trait（install_logger——讀清單／寫快照／收尾；include once）
require_once __DIR__ . "/install.logger.php";

// == HKAI setup sections/install install.php：install 格本體（class＋view／動作 trait——側檔＝install.view.php／install.action.php／install.logger.php；檢查項對映＝sections 域 get_section_unit_instance.php；呼叫形 setup()->sections()->install()）
// == 職責：本格整件——view trait＝head＋body＋footer（＋行渲染）；安裝流程＝清單／狀態／單步執行／結果記錄／收尾（由 setup.cls.php 搬入）
// == 主掣：安裝（動作指令——一撳逐項一項一項執行〔每項即跑即回〕）→ 完成（完成標記寫咗之後——去⑥完成頁）；底列＝左＝上一步／右＝主掣（完成後冇上一步）
class install_Class
{
    use install_view;
    use install_action;
    use install_logger;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {
    }

    // 安裝步驟（第⑤層「安裝」——清單＝過程檔記錄項（setup_process.json；已收尾＝讀 setup_done.json 同一份）＋系統必需步（頭＝環境檔；資料庫檔＋表結構〔缺一＝系統運行唔到——唔靠記錄〕；尾＝完成標記））
    // 記錄項名＝欄位名＝單元 accessor 名（一 key 走通全鏈——直呼單元）；行值＝單元自決（get_install_step_data——記錄值／解密遮罩／留空字眼）
    public function get_install_steps()
    {
        $install_section = setup()->sections()->install();

        $install_steps = [];

        // 頭步：建立環境檔 .env（系統必需步——檔未建立先建立；值一律後補）
        $install_steps[] = $this->build_install_step("prerequisite", "environment__env_writable");

        // 資料庫必需步（建立／確保資料庫——檔＋表結構；唔靠記錄——本格要裝嘅嘢）
        $database_check_item_codes = ["db_ready"];

        $setup_process    = setup()->state()->read_setup_process();
        $process_sections = $setup_process["sections"];

        // 安裝目標格（install 格＝安裝結果記錄——唔係安裝目標，先剔除）
        unset($process_sections["install"]);

        // 逐格（跟過程檔次序——記錄咗嘅先係要安裝嘅嘢）
        foreach ($process_sections as $section_id => $section_recorded_items) {
            $section_check_item_codes = array_keys($section_recorded_items);

            // 判定：資料庫格 → 記錄一律唔用（本格要裝嘅＝必需步——位置照跟本格）
            if ($section_id === "database") {
                $section_check_item_codes = $database_check_item_codes;
            }

            // 逐個項（項名＝單元 accessor 名——直呼單元；行值＝單元自決）
            foreach ($section_check_item_codes as $section_check_item_code_item) {
                $install_steps[] = $this->build_install_step($section_id, $section_check_item_code_item);
            }
        }

        // 判定：過程檔完全冇資料庫格 → 必需步照出（系統運行必需——補喺尾步之前）
        if (!array_key_exists("database", $process_sections)) {
            // 逐個必需項（照出）
            foreach ($database_check_item_codes as $database_check_item_code_item) {
                $install_steps[] = $this->build_install_step("database", $database_check_item_code_item);
            }
        }

        // 尾步：寫入完成標記（最後一步——過程檔收尾改名 setup_done.json；寫咗＝本精靈不再自動出現）
        $install_steps[] = $this->build_install_step("install", "install_marker");

        // 清單快照入 installation.json（進安裝＝寫——供追蹤邊度砌、砌咩；結果跟住逐項入）
        $install_section->save_installation_log($install_steps);

        return $install_steps;
    }

    // 砌安裝步（格＋項名 → 單元自決行資料——標題／行值；頭尾步／記錄項／必需步共用同一形）
    private function build_install_step($install_section_id, $install_check_item_code)
    {
        $unit_instance  = setup()->sections()->get_section_unit_instance($install_section_id, $install_check_item_code);
        $unit_step_data = $unit_instance->get_install_step_data();

        return [
            "section_id"      => $install_section_id,
            "check_item_code" => $install_check_item_code,
            "label"           => $unit_step_data["label"],
            "value"           => $unit_step_data["value"],
        ];
    }

}
