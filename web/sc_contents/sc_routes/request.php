<?php
// == sc_routes/request.php：request 組路由集中檔（由 bootstrapper load_routes 載入） ==
// == 組別：request（session + CSRF——寫入類請求，對應 sc_kernel middlewareGroups "request"） ==
// == url_path 固定結構 path（前綴匹配、最長勝出；未命中 → 404） ==
// == route_path = sc_request/index.php（通用分派器——各域逐行註冊，共用同一檔） ==
// == 動作白名單由各域 plugin __init__.php 動態註冊（未註冊 action → 拒絕） ==

// POST 路由（Laravel Route::post 對應）
sc_router()->register_routes([
    "group"  => "request",
    "method" => "post",
], [
    ["/request/hkai", SC_DIR_REQUEST . "/index.php"],
]);

// GET 路由（輕量讀取用——health_check 心跳式檢查；非 POST 不需 CSRF）
sc_router()->register_routes([
    "group"  => "request",
    "method" => "get",
], [
    ["/request/hkai", SC_DIR_REQUEST . "/index.php"],
]);
