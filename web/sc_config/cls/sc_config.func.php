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
* @param string $key 點分隔巢狀 key
* @param mixed $default 無值回傳
* @return mixed
*/
function sc_get_config($key, $default = null)
{
    $repository = sc_config();
    
    return $repository->get($key, $default);
}

/**
* 讀取全部配置（大陣列原形——唔使 key）
*
* @return array
*/
function sc_get_configs()
{
    $repository = sc_config();
    
    return $repository->get_all();
}

// 寫入 config 單鍵（運行時覆寫——如語言/時區切換）
function sc_set_config($key, $value)
{
    $repository = sc_config();
    
    $repository->set($key, $value);
}

/**
* 設定配置（大陣列一次過——load.php 交入嘅最新全站配置；重複者逐個覆蓋值檔）
*
* @param array $config 配置陣列（return 陣列形）
*/
function sc_set_configs($config)
{
    sc_config_Class::set_latest_config($config);
}
