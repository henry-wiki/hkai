<?php

namespace sc\setup\sections;

// == HKAI setup sections completed view：completed 格 view 側（trait——掛入 completed_Class；檔＝本格 view）
// == 內容：五個 view 方法＝build_section／build_body／build_body_done／build_body_pending／build_footer
// == 載入：completed.php require_once（include once）

trait completed_view
{
    // 出本格整件（head＋body＋footer——合成一個 array 交外殼；外殼只負責展示）
    public function build_section()
    {
        $section_nav = setup()->sections()->get_section_nav("completed");
        
        $section_lines   = [];
        $section_head_text = setup()->i18n()->get("section.completed.head");

        $section_lines[] = "<div class='setup_section-head'>" . $section_head_text . "</div>";
        
        $body_lines = $this->build_body();
        
        // 逐行入列（一行一動作——統一 $section_lines[] 形）
        foreach ($body_lines as $body_line_item) {
            $section_lines[] = $body_line_item;
        }
        
        $footer_lines = $this->build_footer($section_nav);
        
        // 逐行入列（同上）
        foreach ($footer_lines as $footer_line_item) {
            $section_lines[] = $footer_line_item;
        }
        
        return $section_lines;
    }
    
    // 出本格 body（二選一：已安裝＝感謝頁／未安裝＝統計摘要）
    public function build_body()
    {
        // 判定：完成標記存在 → 出完成頁
        if (setup()->state()->is_done()) {
            return $this->build_body_done();
        }
        
        // 其餘（未安裝）→ 出統計摘要
        return $this->build_body_pending();
    }
    
    // 出已安裝 body（感謝頁——logo＋感謝語＋關閉掣；關閉＝重新載入當前頁〔完成後入口直去聊天列表〕）
    public function build_body_done()
    {
        $logo = sc_get_config("view.logo");

        $setup_logo_url  = setup()->url()->sc_to_web_path($logo);
        $product_version = setup()->get_version();

        $completed_thanks = setup()->i18n()->get("completed.thanks");
        $completed_thanks = htmlspecialchars($completed_thanks, ENT_QUOTES);

        $completed_close = setup()->i18n()->get("completed.close");
        $completed_close = htmlspecialchars($completed_close, ENT_QUOTES);

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-main'>";

        $section_lines[] = "<div class='check_hero'>";
        $section_lines[] = "<img class='check_hero__icon' src='" . $setup_logo_url . "' alt='HKAI'>";
        $section_lines[] = "<p class='check_hero__desc'>" . $completed_thanks . "</p>";
        $section_lines[] = "<button type='button' class='button button--primary' data-setup_close>" . $completed_close . "</button>";
        $section_lines[] = "<div class='check_hero__version'>" . $product_version . "</div>";
        $section_lines[] = "</div>";

        $section_lines[] = "</div>";

        return $section_lines;
    }

    // 出未安裝 body（統計摘要——檢查結果＋完成標記兩卡）
    public function build_body_pending()
    {
        // 即場跑全部檢查（單一來源＝前置格檢查群——同格內檢查同一批 runner）
        $check_item_results = setup()->sections()->prerequisite()->run_check_groups();

        // 逐項計數（通過／要處理／提醒）
        $count_pass = 0;
        $count_fail = 0;
        $count_warn = 0;

        // 逐項歸類
        foreach ($check_item_results as $check_item_result_item) {
            $item_status = $check_item_result_item["status"];

            // 判定：通過 → 計通過
            if ($item_status === "pass") {
                $count_pass++;
            }

            // 判定：提醒 → 計提醒
            if ($item_status === "warn") {
                $count_warn++;
            }

            // 判定：其餘（fail／未知）→ 計要處理
            if (!in_array($item_status, ["pass", "warn"])) {
                $count_fail++;
            }
        }

        // 統計文字（先計好——唔喺卡片拼接中塞運算）
        $counts_pass_prefix = setup()->i18n()->get("section.completed.counts_pass_prefix");
        $counts_handle_mid  = setup()->i18n()->get("section.completed.counts_handle_mid");
        $counts_notice_mid  = setup()->i18n()->get("section.completed.counts_notice_mid");
        $counts_suffix      = setup()->i18n()->get("section.completed.counts_suffix");
        $counts_detail_text = $counts_pass_prefix . $count_pass . $counts_handle_mid . $count_fail . $counts_notice_mid . $count_warn . $counts_suffix;
        $counts_hint_text   = setup()->i18n()->get("section.completed.counts_hint");

        // 檢查結果卡狀態（有必解項 → 紅；否則綠）
        $counts_status = "pass";

        // 判定：有必解項 → 轉紅
        if ($count_fail > 0) {
            $counts_status = "fail";
        }

        // 檢查結果卡（三態內容齊——供卡片渲染）
        $check_counts_item = [];
        $check_counts_item["id"]             = "check_counts";
        $check_counts_item["label"]          = setup()->i18n()->get("section.completed.check_counts_label");
        $check_counts_item["status"]         = $counts_status;
        $check_counts_item["result_content"] = [
            "pass" => ["detail" => $counts_detail_text, "hint" => $counts_hint_text],
            "warn" => ["detail" => $counts_detail_text, "hint" => $counts_hint_text],
            "fail" => ["detail" => $counts_detail_text, "hint" => $counts_hint_text],
        ];
        $check_counts_item["detail"]         = $counts_detail_text;
        $check_counts_item["hint"]           = $counts_hint_text;

        // 完成標記卡（單一旗標——完成標記檔存唔存在）
        $marker_status = "warn";
        $marker_detail = setup()->i18n()->get("section.completed.marker_pending");

        // 判定：已標記 → 綠
        if (setup()->state()->is_done()) {
            $marker_status = "pass";
            $marker_detail = setup()->i18n()->get("section.completed.marker_done");
        }

        $setup_marker_item = [];
        $setup_marker_item["id"]             = "setup_marker";
        $setup_marker_item["label"]          = setup()->i18n()->get("section.completed.marker_label");
        $setup_marker_item["status"]         = $marker_status;
        $setup_marker_item["result_content"] = [
            "pass" => ["detail" => $marker_detail, "hint" => ""],
            "warn" => ["detail" => $marker_detail, "hint" => ""],
            "fail" => ["detail" => $marker_detail, "hint" => ""],
        ];
        $setup_marker_item["detail"]         = $marker_detail;
        $setup_marker_item["hint"]           = "";

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-main'>";
            $section_intro_text = setup()->i18n()->get("section.completed.install_not_run");

            $section_lines[] = "<p class='check_intro'>" . $section_intro_text . "</p>";
            $section_lines[] = setup()->view()->render_setup_check($check_counts_item);
            $section_lines[] = setup()->view()->render_setup_check($setup_marker_item);
        $section_lines[] = "</div>";

        return $section_lines;
    }

    // 出本格底列（兩態——已安裝＝唔出底列〔感謝頁自足·只有關閉掣〕／未安裝＝重新檢查＋上一步）
    public function build_footer($section_nav)
    {
        // 判定：完成標記已寫 → 唔出底列（感謝頁自足）
        if (setup()->state()->is_done()) {
            return [];
        }

        $prev_section_id = $section_nav["prev_section_id"];

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-foot'>";
        $section_lines[] = "<div class='setup_section-foot__left'>";
            $recheck_text = setup()->i18n()->get("common.recheck");
            $checking_text = setup()->i18n()->get("common.checking");

            $section_lines[] = "<button type='button' class='button' data-setup_nav='completed' data-nav_action='refresh'>";
            $section_lines[] = "<span class='setup_foot_refresh_idle'>" . $recheck_text . "</span>";
            $section_lines[] = "<span class='setup_foot_refresh_loading'>" . $checking_text . "</span>";
            $section_lines[] = "</button>";
            $section_lines[] = "<span class='check_counts'></span>";
        $section_lines[] = "</div>";

        $section_lines[] = "<div class='setup_section-foot__right'>";

            $prev_step_text = setup()->i18n()->get("common.prev_step");

            // 判定：唔係第一格 → 出上一步
            if (!$section_nav["is_first"]) {
                $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev'>" . $prev_step_text . "</button>";
            }

        $section_lines[] = "</div>";
        $section_lines[] = "</div>";

        return $section_lines;
    }
}
