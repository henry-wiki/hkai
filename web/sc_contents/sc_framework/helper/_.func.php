<?php

// == helpers 對外 API（bridging：回傳 class 單例） ==
// == 規則：bridging 只放域根檔，子目錄不放 bridging ==

use sc\framework\helper\web;

/**
 * 取得資源（asset）管理模組單例
 * @return web\sc_web_asset_Class
 */
function sc_web_asset()
{
    return web\sc_web_asset_Class::getInstance();
}
