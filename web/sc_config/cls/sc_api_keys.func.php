<?php

use sc\config\sc_api_keys_Class;

// == sc_api_keys bridging：sc_api_keys.func.php（cls 根——對外 API 全部委派 sc_api_keys_Class 單例）
// == 規則：本檔零實作——每函數＝一線委派；邏輯本體一律喺 cls/sc_api_keys.cls.php
// == 載入：sc_config/load.php require（全域函數就位）

require_once __DIR__ . "/sc_api_keys.cls.php";

/**
 * 取得 API key 儲存單例
 *
 * @return \sc\config\sc_api_keys_Class
 */
function sc_api_keys()
{
    return sc_api_keys_Class::getInstance();
}
