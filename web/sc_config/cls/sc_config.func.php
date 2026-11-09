<?php
use sc\config\sc_config_Class;

// == sc_config bridging：sc_config.func.php（cls 根——對外 API 全部委派 sc_config_Class 單例） ==
// == 規則：本檔零實作——每函數＝一線委派；邏輯本體一律喺 cls/sc_config.cls.php ==
// == 載入：sc_config/load.php require（全域函數就位） ==

require_once __DIR__ . "/sc_config.cls.php";

/**
* 取得 config 載入器單例
* @return \sc\config\sc_config_Class
*/
function sc_config()
{
    return sc_config_Class::getInstance();
}

/**
* 讀取配置（sc_get_config('web_root')／sc_get_config('contents.dir')）
*
* @param string $config_chain 點分隔巢狀 key
* @param mixed $default 無值回傳
* @return mixed
*/
function sc_get_config($config_chain, $default = null)
{
    return sc_config()->get_config($config_chain, $default);
}

/**
* 寫入 config 單鍵（運行時覆寫——如資料庫路徑注入）
*
* @param string $config_chain 點分隔巢狀 key
* @param mixed $value 值
* @return bool 成功 true／空 chain false
*/
function sc_set_config($config_chain, $value)
{
    return sc_config()->set_config($config_chain, $value);
}

/**
* 設定配置（大陣列一次過——新 key 加入、重複 key 覆蓋（merge））
*
* @param array $configs 配置陣列（return 陣列形）
* @return bool 成功 true／非陣列 false
*/
function sc_set_configs($configs)
{
    return sc_config()->merge_configs($configs);
}


/**
* 讀用戶設定（settings 樹——group.name 或單層 key）
*
* @param string $setting_chain 點分隔設定 key
* @param mixed $default 無值回傳
* @return mixed
*/
function sc_get_setting($setting_chain, $default = null)
{
    return sc_config()->get_setting($setting_chain, $default);
}

/**
* 寫用戶設定單鍵（group.name——寫入 <data>/config/settings.json）
*
* @param string $setting_chain 點分隔設定 key（必須兩段）
* @param mixed $value 值
* @return bool 成功 true／格式錯 false
*/
function sc_set_setting($setting_chain, $value)
{
    return sc_config()->set_setting($setting_chain, $value);
}

/**
* 合併用戶設定（大陣列一次過——新 key 加入、重複 key 覆蓋；寫 settings.json）
*
* @param array $settings 設定陣列（group → key 兩層）
* @return bool 成功 true／非陣列 false
*/
function sc_set_settings($settings)
{
    return sc_config()->merge_settings($settings);
}
