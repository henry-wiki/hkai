<?php
// == sc_app/shared/func/assets.func.php：共享資源註冊（本地 vendor 庫——group "*" = global 永遠載入） ==
// == 由 sc_boot_load_routes 的 sc_app func 載入（re:\.(cls|func)\.php$）自動執行一次 ==
// == register_assets：整組註冊（group → [name => 來源]）；"*" 組由 get_embed_assets 自動輸出 ==
// == CDN URL 原樣保留（sc_to_web_path 對非 docroot 路徑不回改） ==

// 全域 vendor 庫（本地 sc_resources/vendor/<lib>/<版本>/——離線可用；所有 app 頁面自動載入）
sc_web_asset()->register_assets("*", [
    "bootstrap_css"    => "/web/sc_contents/sc_resources/vendor/bootstrap/5.3.3/bootstrap.min.css",
    "bootstrap_js"     => "/web/sc_contents/sc_resources/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js",
    "jquery"           => "/web/sc_contents/sc_resources/vendor/jquery/3.7.1/jquery.min.js",
    "fontawesome_css"  => "/web/sc_contents/sc_resources/vendor/fontawesome/6.7.2/css/all.min.css",
    // toastr（通知 toast——需 jQuery；排在 jquery 後載入）
    "toastr_css"       => "/web/sc_contents/sc_resources/vendor/toastr/2.1.4/toastr.min.css",
    "toastr_js"        => "/web/sc_contents/sc_resources/vendor/toastr/2.1.4/toastr.min.js",
    // SweetAlert2（sc_alert 引擎——alert／confirm UI；CSS＋JS 分開載——sc_alert.css 覆蓋排其後）
    "sweetalert2_css"  => "/web/sc_contents/sc_resources/vendor/sweetalert2/11/sweetalert2.min.css",
    "sweetalert2_js"   => "/web/sc_contents/sc_resources/vendor/sweetalert2/11/sweetalert2.min.js",
    // 共用樣式（本地——sc_app/shared；全 app 自動載入）
    "sc_btn_css"       => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_btn.css"),
    "sc_form_css"      => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_form.css"),
    "sc_empty_state_css" => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_empty_state.css"),
    "sc_table_css"     => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_table.css"),
    // sc_alert（通用訊息彈窗——modal ＋ toast；css 在 shared/jsmods/sc_alert）
    "sc_alert_css"     => sc_to_web_path(SC_DIR_APP . "/shared/jsmods/sc_alert/sc_alert.css"),
]);
