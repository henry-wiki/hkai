<?php

namespace sc\setup;

// == HKAI setup 載入閘：load.php（web/setup.php include_once 本檔——setup 程序嘅實質主檔）
// == 職責：載入 cls 全部 → 網址分派（首段＝setup_request → 交 request.php；其餘 → setup()->view()->load_setup_page()）
// == 語法下限：PHP 5.4（cls 全檔 namespace sc\setup）

// 載入 cls 全部（setup.func.php＝域入口——內部 require 核心 class＋①–④＋view／request；兩路皆需）
require_once __DIR__ . "/cls/setup.func.php";

// 網址首段（url 域解析——REQUEST_URI 去 query／去網址基準／切段；冇段＝空字串）
$request_first_segment = setup()->url()->get_segment(0);

// 判定：首段＝setup_request → 交 request 閘（端點自行回應＋收工）
if ($request_first_segment === "setup_request") {
    require __DIR__ . "/request.php";
    return;
}

// 出畫面＋開檢（唯一一句——setup 根 → view 域）
setup()->view()->load_setup_page();
