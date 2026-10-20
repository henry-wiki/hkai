<?php

namespace sc\setup\sections\setting\provider;

// == HKAI setup setting provider _.bridge.cls.php：provider 域本體（系統指定橋檔——單元載單）
// == 用法：setting_provider_Class::getInstance()->openrouter()——直取檢查單元
// == 一項一檔：provider__openrouter／provider__kilocode（檔名＝class 前綴）
// == 載入：setting/setting.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

class setting_provider_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\setting\provider\setting_provider_Class
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
     * OpenRouter API key（provider 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\setting\provider__openrouter_Class
     */
    public function openrouter()
    {
        require_once __DIR__ . "/provider__openrouter.php";

        return \sc\setup\sections\setting\provider__openrouter_Class::getInstance();
    }

    /**
     * Kilo Code API key（provider 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\setting\provider__kilocode_Class
     */
    public function kilocode()
    {
        require_once __DIR__ . "/provider__kilocode.php";

        return \sc\setup\sections\setting\provider__kilocode_Class::getInstance();
    }
}
