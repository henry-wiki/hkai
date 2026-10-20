<?php

namespace sc\setup;

// == HKAI setup 轉語言端點：switch_language.php（route——入站呼叫交 request 域；class 本體＝cls/request/switch_language.php）
// == 位置：request/（入站呼叫——網址＝setup_request/{動作名}——request.php 分派；經載入鏈自動接駁全部檢查單元）
// == 職責：POST formdata[welcome][language][value]＋section=<id> → 即時記入過程檔＋以新語言重出整件
// == 語法下限：PHP 5.4（同 load.php）

// 交 request 域 class（單一動作——回應＋收工由動作本體出）
setup()->request()->switch_language();
