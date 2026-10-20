<?php

namespace sc\setup;

// == HKAI setup 單項安裝端點：start_single_install.php（route——入站呼叫交 request 域；class 本體＝cls/request/start_single_install.php）
// == 位置：request/（入站呼叫——網址＝setup_request/{動作名}——request.php 分派；經載入鏈自動接駁全部安裝單元）
// == 職責：POST formdata[section_id][check_item_code] → 單元按 code 跑安裝 → 回該行 HTML（{ok, html:{item}, install_ok}）
// == 語法下限：PHP 5.4（同 load.php）

// 交 request 域 class（單一動作——回應＋收工由動作本體出）
setup()->request()->start_single_install();
