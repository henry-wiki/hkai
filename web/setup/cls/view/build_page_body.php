<?php

namespace sc\setup\view;

// == HKAI setup view build_page_body：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：body 段——content＞modal 兩層包裝＋左｜右兩件呼叫（<body> 開——由 build_page_foot() 收）

trait build_page_body
{
    // 出 body 段（content＞modal＞left｜right——即開 <body>）
    public function build_page_body($sections)
    {
        $web_root_disk = dirname(__DIR__, 3);
        $web_base_url  = setup()->url()->sc_to_web_path($web_root_disk);

        $page_body   = [];
        $page_body[] = "<body data-web_base='" . $web_base_url . "'>";

        // body > content
        $page_body[] = "<div class='setup_content'>";

        // content > modal
        $page_body[] = "<div class='setup_modal'>";

        // 目前格（入口未指定——裁決交 sections 域；切換由前端 fetch_section 後重繪、唔經 URL）
        $active_section_id = setup()->sections()->get_section_id("");

        // modal left（六格 nav 掣——section 註冊表）
        $modal_left_lines = setup()->view()->build_modal_left($sections, $active_section_id);

        // 逐行入列（一行一動作——統一 $page_body[] 形）
        foreach ($modal_left_lines as $modal_left_line_item) {
            $page_body[] = $modal_left_line_item;
        }

        // modal right（目前格——單格 section 單元）
        $modal_right_lines = setup()->view()->build_modal_right($sections, $active_section_id);

        // 逐行入列（同上）
        foreach ($modal_right_lines as $modal_right_line_item) {
            $page_body[] = $modal_right_line_item;
        }

        $page_body[] = "</div>";    // 收 modal
        $page_body[] = "</div>";    // 收 content

        return $page_body;
    }
}
