<?php

namespace sc\setup\sections;

// == HKAI setup sections database view：database 格嘅 view 側（trait——掛入 database_Class；檔＝本格嘅 view）
// == 內容：三個 view 方法＝build_section／build_body／build_footer
// == 載入：database/database.php require_once（include once）

trait database_view
{
    // 出本格整件（head＋body＋footer——合成一個 array 交外殼；外殼只負責展示）
    public function build_section()
    {
        $database_section = setup()->sections()->database();

        $section_nav = setup()->sections()->get_section_nav("database");
        
        $section_lines   = [];
        $section_head_text = setup()->i18n()->get("section.database.head");

        $section_lines[] = "<div class='setup_section-head'>" . $section_head_text . "</div>";
        
        $body_lines = $database_section->build_body();
        
        // 逐行入列（一行一動作——統一 $section_lines[] 形）
        foreach ($body_lines as $body_line_item) {
            $section_lines[] = $body_line_item;
        }
        
        $footer_lines = $database_section->build_footer($section_nav);
        
        // 逐行入列（同上）
        foreach ($footer_lines as $footer_line_item) {
            $section_lines[] = $footer_line_item;
        }
        
        return $section_lines;
    }

    // 出本格 body（區塊＋引言＋逐項卡——本格自己跑檢查表）
    public function build_body()
    {
        $database_section = setup()->sections()->database();

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-main'>";
            $section_intro_text = setup()->i18n()->get("section.database.intro");

            $section_lines[] = "<p class='check_intro'>" . $section_intro_text . "</p>";
            
            // 逐項出殼（init 態——唔跑檢查；交前端逐項查、換真卡）
            $check_item_codes = array_keys($database_section->check_items);
            
            foreach ($check_item_codes as $check_item_code) {
                $section_lines[] = setup()->view()->get_check_item_template($check_item_code);
            }
            
        $section_lines[] = "</div>";
        
        return $section_lines;
    }

    // 出本格底列（兩組——左＝重新檢查＋報數／右＝上一步·下一步；每格獨立決定出咩掣）
    public function build_footer($section_nav)
    {
        $prev_section_id = $section_nav["prev_section_id"];
        $next_section_id = $section_nav["next_section_id"];

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-foot'>";
        $section_lines[] = "<div class='setup_section-foot__left'>";
            $recheck_text = setup()->i18n()->get("common.recheck");
            $checking_text = setup()->i18n()->get("common.checking");

            $section_lines[] = "<button type='button' class='button' data-setup_nav='database' data-nav_action='refresh'>";
            $section_lines[] = "<span class='setup_foot_refresh_idle'>" . $recheck_text . "</span>";
            $section_lines[] = "<span class='setup_foot_refresh_loading'>" . $checking_text . "</span>";
            $section_lines[] = "</button>";
            $section_lines[] = "<span class='check_counts'></span>";
        $section_lines[] = "</div>";

        $section_lines[] = "<div class='setup_section-foot__right'>";

            $prev_step_text = setup()->i18n()->get("common.prev_step");
            $next_step_text = setup()->i18n()->get("common.next_step");

            // 判定：第一格 → 上一步停用
            if ($section_nav["is_first"]) {
                $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev' disabled>" . $prev_step_text . "</button>";
            }

            // 判定：唔係第一格 → 正常上一步
            if (!$section_nav["is_first"]) {
                $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev'>" . $prev_step_text . "</button>";
            }

            // 判定：唔係最後一格 → 出下一步
            if (!$section_nav["is_last"]) {
                $section_lines[] = "<button type='button' class='button button--primary' data-setup_nav='" . $next_section_id . "' data-nav_action='next'>" . $next_step_text . "</button>";
            }

        $section_lines[] = "</div>";
        $section_lines[] = "</div>";

        return $section_lines;
    }
}
