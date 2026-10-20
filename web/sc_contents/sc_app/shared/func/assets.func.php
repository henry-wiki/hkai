<?php
// == sc_app/shared/func/assets.func.php：共享資源註冊（全域 CDN 庫——group "*" = global 永遠載入） ==
// == 由 sc_boot_load_routes 的 sc_app func 載入（re:\.(cls|func)\.php$）自動執行一次 ==
// == register_assets：整組註冊（group → [name => 來源]）；"*" 組由 get_embed_assets 自動輸出 ==
// == CDN URL 原樣保留（sc_to_web_path 對非 docroot 路徑不回改） ==

// 全域 CDN 庫（jsDelivr——純 PHP 無打包，原始檔案直出；所有 app 頁面自動載入）
sc_web_asset()->register_assets("*", [
    "bootstrap_css"    => "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
    "bootstrap_js"     => "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js",
    "jquery"           => "https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js",
    "fontawesome_css"  => "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css",
    // toastr（通知 toast——需 jQuery；排在 jquery 後載入）
    "toastr_css"       => "//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css",
    "toastr_js"        => "//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js",
    // SweetAlert2（sc_alert 引擎——alert／confirm UI；CSS＋JS 分開載——sc_alert.css 覆蓋排其後）
    "sweetalert2_css"  => "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css",
    "sweetalert2_js"   => "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js",
    // 共用樣式（本地——sc_app/shared；全 app 自動載入）
    "sc_btn_css"       => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_btn.css"),
    "sc_form_css"      => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_form.css"),
    "sc_empty_state_css" => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_empty_state.css"),
    "sc_table_css"     => sc_to_web_path(SC_DIR_APP . "/shared/styles/css/sc_table.css"),
    // sc_alert（通用訊息彈窗——modal ＋ toast；css 在 shared/jsmods/sc_alert）
    "sc_alert_css"     => sc_to_web_path(SC_DIR_APP . "/shared/jsmods/sc_alert/sc_alert.css"),
]);
