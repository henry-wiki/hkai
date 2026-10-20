<?php

namespace sc\setup;

// == HKAI setup 核心：setup_Class（singleton——對外一律行 setup.func.php bridging）
// == 職責：檢查核心全體——路徑／檢查表／共用小工具／執行／卡片渲染（過程檔／完成標記＝state 域）
// == 規格：零框架（唔用 autoloader／session／DB——只用 PHP 內建）；語法下限 PHP 5.4
// == 寫入：檢查全程唯讀——寫入只喺安裝流程同 state 域（逐項建空檔／寫 .env 值／寫安裝清單／過程檔——純編碼）

class setup_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\setup_Class
     */
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

    // ---------------------------------------------------------
    // 域接駁（向下——sections／view／request／state／i18n／encrypter 單例；各自 class 住域內檔）
    // ---------------------------------------------------------
    /**
     * sections 域單例（向下接駁——六格橋）
     *
     * @return \sc\setup\sections\sections_Class
     */
    public function sections()
    {
        // 載入 sections 域本體＋域 func（cls 本體；func 載 setup() 短名——include once）
        require_once __DIR__ . "/sections/sections.cls.php";
        require_once __DIR__ . "/sections/sections.func.php";

        return \sc\setup\sections\sections_Class::getInstance();
    }

    /**
     * view 域單例（向下接駁——畫面域）
     *
     * @return \sc\setup\view\view_Class
     */
    public function view()
    {
        // 載入 view 域本體＋域 func（cls 本體；func 載 setup() 短名——include once）
        require_once __DIR__ . "/view/view.cls.php";
        require_once __DIR__ . "/view/view.func.php";

        return \sc\setup\view\view_Class::getInstance();
    }

    /**
     * request 域單例（向下接駁——入站動作域）
     *
     * @return \sc\setup\request\request_Class
     */
    public function request()
    {
        // 載入 request 域本體＋域 func（cls 本體；func 載 setup() 短名——include once）
        require_once __DIR__ . "/request/request.cls.php";
        require_once __DIR__ . "/request/request.func.php";

        return \sc\setup\request\request_Class::getInstance();
    }

    /**
     * i18n 域單例（向下接駁——語言域）
     *
     * @return \sc\setup\i18n\i18n_Class
     */
    public function i18n()
    {
        // 載入 i18n 域本體＋域 func（cls 本體；func 載 setup() 短名——include once）
        require_once __DIR__ . "/i18n/i18n.cls.php";
        require_once __DIR__ . "/i18n/i18n.func.php";

        return \sc\setup\i18n\i18n_Class::getInstance();
    }

    /**
     * encrypter 單例（encrypter 域——敏感值加解密：入過程檔前加密／出 .env 前解密）
     *
     * @return \sc\setup\encrypter\encrypter_Class
     */
    public function encrypter()
    {
        // 載入 encrypter 域本體＋域 func（cls 本體；func 載 setup() 短名——include once）
        require_once __DIR__ . "/encrypter/encrypter.cls.php";
        require_once __DIR__ . "/encrypter/encrypter.func.php";

        return \sc\setup\encrypter\encrypter_Class::getInstance();
    }

    /**
     * state 域單例（向下接駁——過程檔／完成標記讀寫）
     *
     * @return \sc\setup\state\state_Class
     */
    public function state()
    {
        // 載入 state 域本體＋域 func（cls 本體；func 載 setup() 短名——include once）
        require_once __DIR__ . "/state/state.cls.php";
        require_once __DIR__ . "/state/state.func.php";

        return \sc\setup\state\state_Class::getInstance();
    }

    /**
     * url 域單例（向下接駁——請求網址段解析）
     *
     * @return \sc\setup\url\url_Class
     */
    public function url()
    {
        // 載入 url 域本體＋域 func（cls 本體；func 載 url() 短名——include once）
        require_once __DIR__ . "/url/url.cls.php";
        require_once __DIR__ . "/url/url.func.php";

        return \sc\setup\url\url_Class::getInstance();
    }

    /**
     * python 域單例（向下接駁——Python 相關操作）
     *
     * @return \sc\setup\python\python_Class
     */
    public function python()
    {
        // 載入 python 域本體＋域 func（cls 本體；func 載 setup()／python() 短名——include once）
        require_once __DIR__ . "/python/python.cls.php";
        require_once __DIR__ . "/python/python.func.php";

        return \sc\setup\python\python_Class::getInstance();
    }
    // 版本（唯一來源＝repo 根 VERSION 檔——bump 版本只改嗰度）
    public function get_version()
    {
        $version = sc_get_config("core.version");

        return trim(file_get_contents($version));
    }

    public function get_section_instance($section_id)
    {
        if($section_id == "")
        {
            return false;
        }
        else if($section_id == "welcome")
        {
            return setup()->sections()->welcome();
        }
        else if($section_id == "prerequisite")
        {
            return setup()->sections()->prerequisite();
        }
        else if($section_id == "settings")
        {
            return setup()->sections()->setting();
        }
        else if($section_id == "database")
        {
            return setup()->sections()->database();
        }
        else if($section_id == "install")
        {
            return setup()->sections()->install();
        }
        else if($section_id == "completed")
        {
            return setup()->sections()->completed();
        }

        return null;
    }

    // 判定：exec 可用（存在＋未列 disable_functions——唔跑子程序嘅快檢）
    public function is_exec_available()
    {
        // 判定：函數唔存在 → 唔可用
        if (!function_exists("exec")) {
            return false;
        }

        $disabled_functions = ini_get("disable_functions");

        // 判定：無設定 → 可用
        if (!is_string($disabled_functions)) {
            return true;
        }

        // 判定：設定為空 → 可用
        if ($disabled_functions === "") {
            return true;
        }

        $disabled_functions_list = explode(",", $disabled_functions);
        $disabled_list           = array_map("trim", $disabled_functions_list);

        // 判定：exec 唔喺停用名單 → 可用
        if (!in_array("exec", $disabled_list)) {
            return true;
        }

        return false;
    }

}
