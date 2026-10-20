<?php

namespace sc\setup\sections\prerequisite\basic;

// == HKAI setup prerequisite basic _.bridge.cls.php：basic 域本體（系統指定橋檔——單元載單）
// == 用法：prerequisite_basic_Class::getInstance()->php_version()——直取檢查單元
// == 一項一檔：basic__xxx（檔名＝class 前綴——形式一樣，複製改名即得）
// == 載入：sections/prerequisite/prerequisite.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

class prerequisite_basic_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\prerequisite\basic\prerequisite_basic_Class
     */
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

    /**
     * PHP 版本（basic 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\basic__php_version_Class
     */
    public function php_version()
    {
        require_once __DIR__ . "/basic__php_version.php";

        return \sc\setup\sections\prerequisite\basic__php_version_Class::getInstance();
    }

    /**
     * 作業系統版本（basic 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\basic__os_version_Class
     */
    public function os_version()
    {
        require_once __DIR__ . "/basic__os_version.php";

        return \sc\setup\sections\prerequisite\basic__os_version_Class::getInstance();
    }

    /**
     * Apache 重寫模組（basic 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\basic__mod_rewrite_Class
     */
    public function mod_rewrite()
    {
        require_once __DIR__ . "/basic__mod_rewrite.php";

        return \sc\setup\sections\prerequisite\basic__mod_rewrite_Class::getInstance();
    }

    /**
     * 記憶體上限（basic 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\basic__memory_limit_Class
     */
    public function memory_limit()
    {
        require_once __DIR__ . "/basic__memory_limit.php";

        return \sc\setup\sections\prerequisite\basic__memory_limit_Class::getInstance();
    }

    /**
     * 最長執行秒數（basic 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\basic__max_execution_time_Class
     */
    public function max_execution_time()
    {
        require_once __DIR__ . "/basic__max_execution_time.php";

        return \sc\setup\sections\prerequisite\basic__max_execution_time_Class::getInstance();
    }
}
