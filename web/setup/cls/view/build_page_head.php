<?php

namespace sc\setup\view;

// == HKAI setup view build_page_head：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：檔頭三段之一——DOCTYPE／html／head（jQuery 本地 assets＋FA CDN＋CSS 引用）

trait build_page_head
{
    // 出檔頭（到 </head> 為止）
    public function build_page_head()
    {
        $setup_css_disk = __DIR__ . "/../../assets/setup.css";
        $setup_css_url  = setup()->url()->sc_to_web_path($setup_css_disk);

        // 版本標記（檔案改動時間——改完 CSS 即刻生效·免瀏覽器食舊 cache）
        $setup_css_url .= "?v=" . filemtime($setup_css_disk);

        $page_head   = [];
        $page_head[] = "<!DOCTYPE html>";
        $page_head[] = "<html lang='zh-Hant'>";
        $page_head[] = "<head>";
        $page_head[] = "<meta charset='UTF-8'>";
        $page_head[] = "<meta name='viewport' content='width=device-width, initial-scale=1'>";
        $sc_loading_css_disk = __DIR__ . "/../../jsmods/sc_loading/sc_loading.css";
        $sc_loading_css_url  = setup()->url()->sc_to_web_path($sc_loading_css_disk);

        // 版本標記（檔案改動時間——改完 CSS 即刻生效·免瀏覽器食舊 cache）
        $sc_loading_css_url .= "?v=" . filemtime($sc_loading_css_disk);

        $page_title = setup()->i18n()->get("page.title");

        $page_head[] = "<title>" . $page_title . "</title>";
        $page_head[] = "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css'>";
        $page_head[] = "<link rel='stylesheet' href='" . $setup_css_url . "'>";
        $page_head[] = "<link rel='stylesheet' href='" . $sc_loading_css_url . "'>";
        $jquery_js_disk = __DIR__ . "/../../assets/jquery.min.js";
        $jquery_js_url  = setup()->url()->sc_to_web_path($jquery_js_disk);

        // 版本標記（檔案改動時間——改完即刻生效·免瀏覽器食舊 cache）
        $jquery_js_url .= "?v=" . filemtime($jquery_js_disk);

        $page_head[] = "<script src='" . $jquery_js_url . "'></script>";
        $page_head[] = "</head>";

        return $page_head;
    }
}
