<?php

namespace sc\setup\sections\prerequisite\permission;

// == HKAI setup prerequisite permission _.bridge.cls.php：permission 域本體（系統指定橋檔——單元載單）
// == 用法：prerequisite_permission_Class::getInstance()->env_writable()——直取檢查單元
// == 一項一檔：environment__xxx（Python 與引擎＝executor/；檔案與權限＝permission/——形式一樣，複製改名即得）
// == 載入：sections/prerequisite/prerequisite.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

class prerequisite_permission_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\prerequisite\permission\prerequisite_permission_Class
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
     * env 可寫（permission 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__env_writable_Class
     */
    public function env_writable()
    {
        require_once __DIR__ . "/environment__env_writable.php";

        return \sc\setup\sections\prerequisite\environment__env_writable_Class::getInstance();
    }

    /**
     * services 目錄（permission 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__services_dir_Class
     */
    public function services_dir()
    {
        require_once __DIR__ . "/environment__services_dir.php";

        return \sc\setup\sections\prerequisite\environment__services_dir_Class::getInstance();
    }

    /**
     * storage 目錄（permission 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__storage_dirs_Class
     */
    public function storage_dirs()
    {
        require_once __DIR__ . "/environment__storage_dirs.php";

        return \sc\setup\sections\prerequisite\environment__storage_dirs_Class::getInstance();
    }

    /**
     * 專案就緒（permission 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__project_ready_Class
     */
    public function project_ready()
    {
        require_once __DIR__ . "/environment__project_ready.php";

        return \sc\setup\sections\prerequisite\environment__project_ready_Class::getInstance();
    }

    /**
     * 磁碟空間（permission 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__disk_space_Class
     */
    public function disk_space()
    {
        require_once __DIR__ . "/environment__disk_space.php";

        return \sc\setup\sections\prerequisite\environment__disk_space_Class::getInstance();
    }
}
