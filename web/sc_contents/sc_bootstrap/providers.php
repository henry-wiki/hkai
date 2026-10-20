<?php
// == providers：外來套件（vendor）初始化集中檔 ==
// == 開發者新增套件初始化：在此加區塊（勿放業務邏輯） ==

// ---------- Whoops（錯誤處理——dev 依賴；CLI 未載 vendor 時跳過） ----------
if (class_exists("\\Whoops\\Run")) {
    $whoopsRun = new \Whoops\Run();
    $whoopsRun->pushHandler(new \Whoops\Handler\PrettyPageHandler());
    $whoopsRun->register();
}

// ---------- 全局 middleware（依 Laravel 全局組順序掛載：代理→CORS→維修→大小→消毒） ----------
sc_kernel()->add_middleware(\sc\framework\foundation\http\middleware\sc_trust_proxies_middleware_Class::getInstance());
sc_kernel()->add_middleware(\sc\framework\foundation\http\middleware\sc_cors_middleware_Class::getInstance());
sc_kernel()->add_middleware(\sc\framework\foundation\http\middleware\sc_maintenance_middleware_Class::getInstance());
sc_kernel()->add_middleware(\sc\framework\foundation\http\middleware\sc_validate_post_size_middleware_Class::getInstance());
sc_kernel()->add_middleware(\sc\framework\foundation\http\middleware\sc_trim_strings_middleware_Class::getInstance());
sc_kernel()->add_middleware(\sc\framework\foundation\http\middleware\sc_convert_empty_strings_middleware_Class::getInstance());

// ---------- Session / CSRF middleware（Laravel 對應：StartSession + VerifyCsrfToken——app 組） ----------
// 已移入 kernel $middlewareGroups["app"]（Laravel 同款：app 組才有 session+CSRF，api 組無）
// 注意：不可同時掛全局＋app 組（執行兩次 → session_set_save_handler 重複 → shutdown 寫入失敗；CSRF 對 api 誤擋）
