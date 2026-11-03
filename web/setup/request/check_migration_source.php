<?php

namespace sc\setup;

// == HKAI setup 舊安裝搬遷掃描端點：check_migration_source.php（route——入站呼叫交 request 域；class 本體＝cls/request/check_migration_source.php）
// == 位置：request/（入站呼叫——網址＝setup_request/{動作名}——request.php 分派）
// == 職責：POST source_dir → {ok, status, message, version}（唯讀）

setup()->request()->check_migration_source();
