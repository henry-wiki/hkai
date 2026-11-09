<?php

use sc\config\sc_data_path_Class;

// == sc_data_path bridging：sc_data_path.func.php（cls 根——對外 API 全部委派 sc_data_path_Class 單例）
// == 規則：本檔零實作——每函數＝一線委派；邏輯本體一律喺 cls/sc_data_path.cls.php

require_once __DIR__ . "/sc_data_path.cls.php";

/**
 * 取得資料路徑總管單例
 *
 * @return \sc\config\sc_data_path_Class
 */
function sc_data_path()
{
    return sc_data_path_Class::getInstance();
}

/**
 * 取得資料根目錄（即時計算——路徑規則本體喺 sc_data_path_Class）
 *
 * @return string
 */
function sc_get_data_dir(): string
{
    return sc_data_path()->get_data_dir();
}
