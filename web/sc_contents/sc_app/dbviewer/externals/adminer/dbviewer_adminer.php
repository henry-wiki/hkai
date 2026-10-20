<?php
// == sc_app/dbviewer/externals/adminer/dbviewer_adminer.php：Adminer 全域掛鉤檔（third-party 橋接——非 autoload 語境） ==
// == 角色：adminer_object() 掛鉤——原版執行時查此函數取定制 instance（存在→用它，否則 new Adminer） ==
// == 載入時機：dbviewer_main_Class 在 require 原版 adminer.php 之前 require 本檔 ==

// 原版 Adminer 執行時查詢此掛鉤取得定制 class instance
function adminer_object()
{
    // 延遲載入定制 class 檔（dbviewer_adminer_Class extends Adminer——查鉤被呼叫時 class Adminer 已定義，extends 才安全）
    require_once __DIR__ . "/dbviewer_adminer_class.php";
    
    return new dbviewer_adminer_Class();
}
