<?php

namespace sc\setup\sections\setting\benchmark;

// == HKAI setup setting benchmark _.bridge.cls.php：benchmark 域本體（系統指定橋檔——單元載單）
// == 用法：setting_benchmark_Class::getInstance()->llm_stats()——直取檢查單元
// == 一項一檔：benchmark__llm_stats／benchmark__artificial_analysis（檔名＝class 前綴）
// == 載入：setting/setting.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

class setting_benchmark_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\setting\benchmark\setting_benchmark_Class
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
     * LLM Stats API key（benchmark 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\setting\benchmark__llm_stats_Class
     */
    public function llm_stats()
    {
        require_once __DIR__ . "/benchmark__llm_stats.php";

        return \sc\setup\sections\setting\benchmark__llm_stats_Class::getInstance();
    }

    /**
     * Artificial Analysis API key（benchmark 單元——單例·按需 require 單元檔）
     *
     * @return \sc\setup\sections\setting\benchmark__artificial_analysis_Class
     */
    public function artificial_analysis()
    {
        require_once __DIR__ . "/benchmark__artificial_analysis.php";

        return \sc\setup\sections\setting\benchmark__artificial_analysis_Class::getInstance();
    }
}
