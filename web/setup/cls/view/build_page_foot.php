<?php

namespace sc\setup\view;

// == HKAI setup view build_page_foot：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：檔尾三段之一——script＋收 body／html

trait build_page_foot
{
    // 出檔尾（script＋收尾——收 build_page_body() 開嘅 <body>）
    public function build_page_foot()
    {
        $setup_js_disk = __DIR__ . "/../../jsmods/bootstrap.js";
        $setup_js_url  = setup()->url()->sc_to_web_path($setup_js_disk);

        // 版本標記（檔案改動時間——改完 JS 即刻生效·免瀏覽器食舊 cache）
        $setup_js_url .= "?v=" . filemtime($setup_js_disk);

        $page_foot   = [];
        $page_foot[] = "<script type='module' src='" . $setup_js_url . "'></script>";
        $page_foot[] = "</body>";
        $page_foot[] = "</html>";

        return $page_foot;
    }
}
