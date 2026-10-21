<?php

// == 共用：PHP 內建 CA bundle 路徑（相對 PHP_BINARY——零寫死；HTTPS curl 用） ==

// 回傳同 PHP 執行檔一齊出貨嘅 cacert.pem 路徑（唔存在＝空字串——交系統預設）
function sc_bundled_cacert_path()
{
    $cacert_path = dirname(PHP_BINARY) . "/extras/ssl/cacert.pem";

    // 判定：檔案唔存在 → 回空字串
    if (!is_file($cacert_path)) {
        return "";
    }

    return $cacert_path;
}
