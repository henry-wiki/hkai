<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup sections/prerequisite prerequisite.php：prerequisite 格（格本體——view 側＝prerequisite.view.php；檢查項對映＝sections 域 get_section_unit_instance）
// == 用法：prerequisite_Class::getInstance()->build_section()／basic()->php_version()／php_extension()->curl()／executor()->pip()／permission()->disk_space()——直取（例）
// == 一項一檔：basic__xxx（basic/ 子目錄）／php_extension__xxx（php_extension/ 子目錄）／environment__xxx（Python 與引擎＝executor/；檔案與權限＝permission/）／initialization__xxx（檔名＝class 前綴——形式一樣，複製改名即得）

// 載入 prerequisite 格內部短名（_.internal.func.php——setup() 向上接駁）
require_once __DIR__ . "/_.internal.func.php";

// 載入 basic 域內部短名（basic/_.internal.func.php——basic() 短名；include once）
require_once __DIR__ . "/basic/_.internal.func.php";

// 載入 php_extension 域內部短名（php_extension/_.internal.func.php——php_extension() 短名；include once）
require_once __DIR__ . "/php_extension/_.internal.func.php";

// 載入 executor 域內部短名（executor/_.internal.func.php——executor() 短名；include once）
require_once __DIR__ . "/executor/_.internal.func.php";

// 載入 permission 域內部短名（permission/_.internal.func.php——permission() 短名；include once）
require_once __DIR__ . "/permission/_.internal.func.php";

// 載入本格 view 側 trait（三個 view 方法——include once）
require_once __DIR__ . "/prerequisite.view.php";

// == HKAI setup sections prerequisite：前置檢查格單元（格本體——class；呼叫形 setup()->sections()->prerequisite()）
// == 職責：本格整件（build_section＝head＋body＋footer——每格獨立決定）；檢查表內置（五組——逐組標題＋徽章＋逐項卡）
// == 徽章跟實況（fail／warn 計數）——force_result 只換卡 layout，唔影響徽章
class prerequisite_Class
{
    use prerequisite_view;

    private static $instance = null;

    // 檢查組表（what to check——一組一 label＋items；items＝一維 code 列（單元初始化＝sections 域 get_section_unit_instance）；由 __construct 砌）
    private $check_groups = [];

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
        // 砌檢查組表（五組——組內 items＝一維 code 列；檢查單元由 sections 域 get_section_unit_instance 初始化）
        $this->check_groups = [];

        // A 組：系統與 PHP
        $group_system = [];
        $group_system["label"] = setup()->i18n()->get("section.prerequisite.group_system");
        $group_system["items"] = [];
        $group_system["items"][] = "basic__php_version";
        $group_system["items"][] = "basic__os_version";
        $group_system["items"][] = "basic__mod_rewrite";
        $group_system["items"][] = "basic__memory_limit";
        $group_system["items"][] = "basic__max_execution_time";
        $this->check_groups[] = $group_system;

        // B 組：PHP 擴展
        $group_extensions = [];
        $group_extensions["label"] = setup()->i18n()->get("section.prerequisite.group_extensions");
        $group_extensions["items"] = [];
        $group_extensions["items"][] = "php_extension__pdo_sqlite";
        $group_extensions["items"][] = "php_extension__curl";
        $group_extensions["items"][] = "php_extension__curl_cainfo";
        $group_extensions["items"][] = "php_extension__openssl";
        $group_extensions["items"][] = "php_extension__sodium";
        $group_extensions["items"][] = "php_extension__mbstring";
        $group_extensions["items"][] = "php_extension__json";
        $this->check_groups[] = $group_extensions;

        // C 組：PHP 函數
        $group_functions = [];
        $group_functions["label"] = setup()->i18n()->get("section.prerequisite.group_functions");
        $group_functions["items"] = [];
        $group_functions["items"][] = "php_extension__exec";
        $group_functions["items"][] = "php_extension__shell_exec";
        $group_functions["items"][] = "php_extension__popen";
        $group_functions["items"][] = "php_extension__pclose";
        $group_functions["items"][] = "php_extension__escapeshellarg";
        $group_functions["items"][] = "php_extension__proc_open";
        $group_functions["items"][] = "php_extension__proc_close";
        $group_functions["items"][] = "php_extension__proc_get_status";
        $group_functions["items"][] = "php_extension__proc_terminate";
        $group_functions["items"][] = "php_extension__putenv";
        $group_functions["items"][] = "php_extension__getenv";
        $group_functions["items"][] = "php_extension__flock";
        $group_functions["items"][] = "php_extension__fsockopen";
        $this->check_groups[] = $group_functions;

        // D 組：Python 與引擎
        $group_python = [];
        $group_python["label"] = setup()->i18n()->get("section.prerequisite.group_python");
        $group_python["items"] = [];
        $group_python["items"][] = "environment__python_executable";
        $group_python["items"][] = "environment__pip";
        $group_python["items"][] = "environment__python_packages";
        $group_python["items"][] = "environment__goose_executable";
        $group_python["items"][] = "environment__aider_pylibs_dir";
        $this->check_groups[] = $group_python;

        // F 組：檔案與權限
        $group_files = [];
        $group_files["label"] = setup()->i18n()->get("section.prerequisite.group_files");
        $group_files["items"] = [];
        $group_files["items"][] = "environment__env_writable";
        $group_files["items"][] = "environment__services_dir";
        $group_files["items"][] = "environment__storage_dirs";
        $group_files["items"][] = "environment__project_ready";
        $group_files["items"][] = "environment__disk_space";
        $this->check_groups[] = $group_files;
    }

    // 跑單一檢查項（按 code 初始化對應檢查單元——跑咗回檢查結果；未知＝null）
    public function run_check_item($check_item_code)
    {
        $check_item_instance = setup()->sections()->get_section_unit_instance("prerequisite", $check_item_code);

        // 判定：未知項 → null
        if ($check_item_instance === null) {
            return null;
        }

        return $check_item_instance->setup_check();
    }

    // 跑全部檢查群（逐組逐項——回 {code: 檢查結果}；供總結統計一次過攞齊用）
    public function run_check_groups()
    {
        $check_item_results = [];

        // 逐組（每組＝label＋items）
        foreach ($this->check_groups as $check_group_item) {
            // 逐項（items＝一維 code 列——單元由 sections 域 get_section_unit_instance 初始化）
            foreach ($check_group_item["items"] as $check_item_code) {
                $check_item_instance = setup()->sections()->get_section_unit_instance("prerequisite", $check_item_code);

                $check_item_results[$check_item_code] = $check_item_instance->setup_check();
            }
        }

        return $check_item_results;
    }

    public function get_state_value($check_item_code='',$value='')
    {
        if($check_item_code === "")
        {
            // == invalid check item code
            return ;
        }

        $state_value = [];
        $state_value["value"] = "";
        $state_value["update_at"] = date("Y-m-d H:i:s");

        if($check_item_code === "environment__python_executable")
        {
            $state_value["value"] = $value;
        }
        else if($check_item_code === "environment__goose_executable")
        {
            $state_value["value"] = $value;
        }
        else if($check_item_code === "environment__env_writable")
        {
            $state_value["value"] = $value;
        }
        else if($check_item_code === "environment__aider_pylibs_dir")
        {
            $state_value["value"] = $value;
        }

        return $state_value;
    }
}
