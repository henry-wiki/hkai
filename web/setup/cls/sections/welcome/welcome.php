<?php

namespace sc\setup\sections;

// 載入本格 view 側 trait（三個 view 方法——include once）
require_once __DIR__ . "/welcome.view.php";

// 載入本格 install 側 trait（安裝制方法——include once）
require_once __DIR__ . "/welcome.install.php";

// == HKAI setup sections welcome：welcome 格單元（class＋view／install trait——側檔＝welcome.view.php／welcome.install.php；呼叫形 setup()->sections()->welcome()）
// == 職責：本格整件（build_section＝head＋body＋footer；body＝置中 hero——icon＋描述＋語言選擇）；冇核心層結果

class welcome_Class
{
    use welcome_view;
    use welcome_install;

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

    /**
     * 語言（本格單例——安裝制記錄項「language」宿主）
     *
     * @return \sc\setup\sections\welcome_Class
     */
    public function language()
    {
        return $this;
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

        if($check_item_code === "language")
        {
            $state_value["value"] = $value;
        }

        return $state_value;
    }



}
