<?php

namespace sc\setup\sections\prerequisite\php_extension;

// == HKAI setup prerequisite php_extension _.bridge.cls.php：php_extension 域本體（系統指定橋檔——單元載單）
// == 用法：prerequisite_php_extension_Class::getInstance()->curl()——直取檢查單元
// == 一項一檔：php_extension__xxx（檔名＝class 前綴——形式一樣，複製改名即得）
// == 載入：sections/prerequisite/prerequisite.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

class prerequisite_php_extension_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\prerequisite\php_extension\prerequisite_php_extension_Class
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
     * 擴展 pdo_sqlite（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__pdo_sqlite_Class
     */
    public function pdo_sqlite()
    {
        require_once __DIR__ . "/php_extension__pdo_sqlite.php";

        return \sc\setup\sections\prerequisite\php_extension__pdo_sqlite_Class::getInstance();
    }

    /**
     * 擴展 curl（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__curl_Class
     */
    public function curl()
    {
        require_once __DIR__ . "/php_extension__curl.php";

        return \sc\setup\sections\prerequisite\php_extension__curl_Class::getInstance();
    }

    /**
     * curl 憑證設定（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__curl_cainfo_Class
     */
    public function curl_cainfo()
    {
        require_once __DIR__ . "/php_extension__curl_cainfo.php";

        return \sc\setup\sections\prerequisite\php_extension__curl_cainfo_Class::getInstance();
    }

    /**
     * 擴展 openssl（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__openssl_Class
     */
    public function openssl()
    {
        require_once __DIR__ . "/php_extension__openssl.php";

        return \sc\setup\sections\prerequisite\php_extension__openssl_Class::getInstance();
    }

    /**
     * 擴展 sodium（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__sodium_Class
     */
    public function sodium()
    {
        require_once __DIR__ . "/php_extension__sodium.php";

        return \sc\setup\sections\prerequisite\php_extension__sodium_Class::getInstance();
    }

    /**
     * 擴展 mbstring（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__mbstring_Class
     */
    public function mbstring()
    {
        require_once __DIR__ . "/php_extension__mbstring.php";

        return \sc\setup\sections\prerequisite\php_extension__mbstring_Class::getInstance();
    }

    /**
     * 擴展 json（php_extension 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__json_Class
     */
    public function json()
    {
        require_once __DIR__ . "/php_extension__json.php";

        return \sc\setup\sections\prerequisite\php_extension__json_Class::getInstance();
    }

    /**
     * 函數 exec（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__exec_Class
     */
    public function exec()
    {
        require_once __DIR__ . "/php_extension__exec.php";

        return \sc\setup\sections\prerequisite\php_extension__exec_Class::getInstance();
    }

    /**
     * 函數 shell_exec（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__shell_exec_Class
     */
    public function shell_exec()
    {
        require_once __DIR__ . "/php_extension__shell_exec.php";

        return \sc\setup\sections\prerequisite\php_extension__shell_exec_Class::getInstance();
    }

    /**
     * 函數 popen（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__popen_Class
     */
    public function popen()
    {
        require_once __DIR__ . "/php_extension__popen.php";

        return \sc\setup\sections\prerequisite\php_extension__popen_Class::getInstance();
    }

    /**
     * 函數 pclose（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__pclose_Class
     */
    public function pclose()
    {
        require_once __DIR__ . "/php_extension__pclose.php";

        return \sc\setup\sections\prerequisite\php_extension__pclose_Class::getInstance();
    }

    /**
     * 函數 escapeshellarg（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__escapeshellarg_Class
     */
    public function escapeshellarg()
    {
        require_once __DIR__ . "/php_extension__escapeshellarg.php";

        return \sc\setup\sections\prerequisite\php_extension__escapeshellarg_Class::getInstance();
    }

    /**
     * 函數 proc_open（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__proc_open_Class
     */
    public function proc_open()
    {
        require_once __DIR__ . "/php_extension__proc_open.php";

        return \sc\setup\sections\prerequisite\php_extension__proc_open_Class::getInstance();
    }

    /**
     * 函數 proc_close（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__proc_close_Class
     */
    public function proc_close()
    {
        require_once __DIR__ . "/php_extension__proc_close.php";

        return \sc\setup\sections\prerequisite\php_extension__proc_close_Class::getInstance();
    }

    /**
     * 函數 proc_get_status（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__proc_get_status_Class
     */
    public function proc_get_status()
    {
        require_once __DIR__ . "/php_extension__proc_get_status.php";

        return \sc\setup\sections\prerequisite\php_extension__proc_get_status_Class::getInstance();
    }

    /**
     * 函數 proc_terminate（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__proc_terminate_Class
     */
    public function proc_terminate()
    {
        require_once __DIR__ . "/php_extension__proc_terminate.php";

        return \sc\setup\sections\prerequisite\php_extension__proc_terminate_Class::getInstance();
    }

    /**
     * 函數 putenv（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__putenv_Class
     */
    public function putenv()
    {
        require_once __DIR__ . "/php_extension__putenv.php";

        return \sc\setup\sections\prerequisite\php_extension__putenv_Class::getInstance();
    }

    /**
     * 函數 getenv（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__getenv_Class
     */
    public function getenv()
    {
        require_once __DIR__ . "/php_extension__getenv.php";

        return \sc\setup\sections\prerequisite\php_extension__getenv_Class::getInstance();
    }

    /**
     * 函數 flock（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__flock_Class
     */
    public function flock()
    {
        require_once __DIR__ . "/php_extension__flock.php";

        return \sc\setup\sections\prerequisite\php_extension__flock_Class::getInstance();
    }

    /**
     * 函數 fsockopen（php_extension 域——經單元橋按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\php_extension__fsockopen_Class
     */
    public function fsockopen()
    {
        require_once __DIR__ . "/php_extension__fsockopen.php";

        return \sc\setup\sections\prerequisite\php_extension__fsockopen_Class::getInstance();
    }
}
