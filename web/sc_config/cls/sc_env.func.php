<?php
use sc\config\sc_env_Class;

// == sc_env bridging：sc_env.func.php（cls 根——對外 API 全部委派 sc_env_Class 單例） ==
// == 規則：本檔零實作——每函數＝一線委派；邏輯本體一律喺 cls/sc_env.cls.php ==
// == 載入：sc_config/load.php require（全域函數就位） ==

require_once __DIR__ . "/sc_env.cls.php";

/**
* 取得 env 載入器單例
* @return \sc\config\sc_env_Class
*/
function sc_env()
{
    return sc_env_Class::getInstance();
}

/**
* 讀取 env 值（sc_get_env('OPENROUTER_API_KEY', '')）
*
* @param string $key .env 鍵
* @param mixed $default 無值回傳
* @return mixed
*/
function sc_get_env($key, $default = null)
{
    $repository = sc_env();
    
    return $repository->get($key, $default);
}

// 寫入 env 單鍵（填值——缺先加·已有唔覆蓋·冇行接尾；寫 .env 檔）
function sc_set_env($key, $value)
{
    $repository = sc_env();
    
    $repository->set($key, $value);
}
