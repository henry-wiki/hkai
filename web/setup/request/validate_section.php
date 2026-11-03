<?php

namespace sc\setup;

// == HKAI setup 下一步前儲存＋驗證端點：validate_section.php（route——入站呼叫交 request 域；class 本體＝cls/request/validate_section.php）
// == 職責：POST section＋formdata → {ok} 或 {ok:false, error, field}

setup()->request()->validate_section();
