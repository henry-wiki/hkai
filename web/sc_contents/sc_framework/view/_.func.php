<?php

// == view 對外 API（bridging：回傳 class 單例／工廠——bridging 集中於域根） ==
// == 集中：sc_view——對外入口一律 global（規則：bridging 只放域根） ==
// == 由 sc_boot_load_framework load_func(sc_framework/view/) 載入 ==

use sc\framework\view;

/**
* 建立延遲渲染 View 物件（Laravel view() helper 對應）
* @param string $template 模板名稱（斜線＝目錄，如 "main/welcome"）
* @param array  $data 資料契約（view 內以變數形式取得）
* @return view\sc_view_Class
*/
function sc_view($template_path, $data = [])
{
    return new view\sc_view_Class($template_path, $data);
}
