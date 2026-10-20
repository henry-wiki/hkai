<?php

namespace sc\setup;

// == HKAI setup 請求閘：request.php（load.php 首段＝setup_request 交入——按第二段分派）
// == 職責：網址第二段（動作名）→ 交 request/{動作}.php（端點本體自行回應＋收工）
// == 語法下限：PHP 5.4（cls 全檔 namespace sc\setup）

// 動作名＝網址第二段（url 域解析——冇＝空）
$request_action_name = setup()->url()->get_segment(1);

// 動作檔（本目錄 request/）
$request_action_file = __DIR__ . "/request/" . $request_action_name . ".php";

// 判定：動作檔唔存在 → 404（唔存在＝唔係有效動作）
if (!is_file($request_action_file)) {
    http_response_code(404);
    return;
}

// 交動作檔（入站呼叫——回應同收工由動作本體出）
require $request_action_file;
