<?php

namespace sc\setup\sections;

// == HKAI setup 安裝單元：install.marker.php（安裝格「完成標記」——單項自足）
// == 職責：呢一項嘅全部——get_install_step_data()＝安裝步驟行資料；setup_install()＝最後一步動作（檔已存在＝保留；未存在＝回 completed）
// == 接入：install 域門（install.func.php::install_marker()——按需 require；sections 域派發用）
// == 載入：顯式（無 autoloader）——install 格檢查項派發按需 require 本檔

class install_marker_Class
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

    // 安裝步驟資料（標題／行值——框架步：冇輸入值，行值留空）
    public function get_install_step_data()
    {
        return ["label" => setup()->i18n()->get("install.step.install_marker"), "value" => ""];
    }

    // 安裝（最後一步——檔已存在＝保留〔重入安全〕；未存在＝回 completed——改名喺端點收尾做）
    public function setup_install()
    {
        $setup_done = sc_get_config("setup.setup_done");

        // 判定：完成檔已存在 → 唔動（保留）
        if (is_file($setup_done)) {
            return ["ok" => true, "result" => "kept", "detail" => setup()->i18n()->get("install.detail.install_marker_kept")];
        }

        return ["ok" => true, "result" => "completed", "detail" => setup()->i18n()->get("install.detail.install_marker_completed")];
    }
}
