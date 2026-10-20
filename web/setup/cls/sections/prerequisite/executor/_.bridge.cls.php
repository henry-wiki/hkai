<?php

namespace sc\setup\sections\prerequisite\executor;

// == HKAI setup prerequisite executor _.bridge.cls.php：executor 域本體（系統指定橋檔——單元載單）
// == 用法：prerequisite_executor_Class::getInstance()->python_executable()——直取檢查單元
// == 一項一檔：environment__xxx（Python 與引擎＝executor/；檔案與權限＝permission/——形式一樣，複製改名即得）
// == 載入：sections/prerequisite/prerequisite.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

class prerequisite_executor_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\prerequisite\executor\prerequisite_executor_Class
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
     * Python 執行檔（executor 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__python_executable_Class
     */
    public function python_executable()
    {
        require_once __DIR__ . "/environment__python_executable.php";

        return \sc\setup\sections\prerequisite\environment__python_executable_Class::getInstance();
    }

    /**
     * pip（executor 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__pip_Class
     */
    public function pip()
    {
        require_once __DIR__ . "/environment__pip.php";

        return \sc\setup\sections\prerequisite\environment__pip_Class::getInstance();
    }

    /**
     * Python 套件（executor 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__python_packages_Class
     */
    public function python_packages()
    {
        require_once __DIR__ . "/environment__python_packages.php";

        return \sc\setup\sections\prerequisite\environment__python_packages_Class::getInstance();
    }

    /**
     * Goose 執行檔（executor 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__goose_executable_Class
     */
    public function goose_executable()
    {
        require_once __DIR__ . "/environment__goose_executable.php";

        return \sc\setup\sections\prerequisite\environment__goose_executable_Class::getInstance();
    }

    /**
     * Aider 套件目錄（executor 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\environment__aider_pylibs_dir_Class
     */
    public function aider_pylibs_dir()
    {
        require_once __DIR__ . "/environment__aider_pylibs_dir.php";

        return \sc\setup\sections\prerequisite\environment__aider_pylibs_dir_Class::getInstance();
    }
}
