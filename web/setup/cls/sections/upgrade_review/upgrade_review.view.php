<?php

namespace sc\setup\sections;

// == HKAI setup sections upgrade_review view：upgrade_review 格嘅 view 側（trait——掛入 upgrade_review_Class；檔＝本格嘅 view）
// == 內容：三個 view 方法＝build_section／build_body／build_footer
// == 載入：upgrade_review/upgrade_review.php require_once（include once）

trait upgrade_review_view
{
    // 出本格整件（head＋body＋footer——合成一個 array 交外殼）
    public function build_section()
    {
        $upgrade_review_section = setup()->sections()->upgrade_review();

        $section_nav = setup()->sections()->get_section_nav("upgrade_review");

        $section_lines   = [];
        $section_head_text = setup()->i18n()->get("section.upgrade_review.head");

        $section_lines[] = "<div class='setup_section-head'>" . $section_head_text . "</div>";

        $body_lines = $upgrade_review_section->build_body();

        // 逐行入列（一行一動作——統一 $section_lines[] 形）
        foreach ($body_lines as $body_line_item) {
            $section_lines[] = $body_line_item;
        }

        $footer_lines = $upgrade_review_section->build_footer($section_nav);

        // 逐行入列（同上）
        foreach ($footer_lines as $footer_line_item) {
            $section_lines[] = $footer_line_item;
        }

        return $section_lines;
    }

    // 出本格 body（來源／偵測版本／目標版本／備份提示——只讀顯示）
    public function build_body()
    {
        $source_dir       = setup()->state()->get_setting_setup_process("welcome", "source_dir");
        $source_scan      = setup()->database_migration_source()->scan_source($source_dir);
        $target_version   = sc_get_config("storage.database_schema_version");

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-main'>";
            $section_intro_text = setup()->i18n()->get("section.upgrade_review.intro");

            $section_lines[] = "<p class='check_intro'>" . $section_intro_text . "</p>";

            $section_lines[] = "<div class='setup_review'>";

                $source_label = setup()->i18n()->get("section.upgrade_review.source");
                $section_lines[] = "<div class='setup_review-row'>";
                    $section_lines[] = "<span class='setup_review-label'>" . $source_label . "</span>";
                    $section_lines[] = "<span class='setup_review-value'>" . sc_html_encode($source_dir) . "</span>";
                $section_lines[] = "</div>";

                $detected_label = setup()->i18n()->get("section.upgrade_review.detected");
                $detected_text  = "";

                // 判定：掃描失敗 → 顯示原因（擋安裝由 validate／install 負責）
                if ($source_scan["ok"] !== true) {
                    $detected_text = setup()->i18n()->get($source_scan["detail_key"]);
                }

                // 判定：掃描成功 → 顯示版本
                if ($source_scan["ok"] === true) {
                    $detected_text = "dbv-" . $source_scan["version"];
                }

                $section_lines[] = "<div class='setup_review-row'>";
                    $section_lines[] = "<span class='setup_review-label'>" . $detected_label . "</span>";
                    $section_lines[] = "<span class='setup_review-value'>" . sc_html_encode($detected_text) . "</span>";
                $section_lines[] = "</div>";

            $section_lines[] = "</div>";

            // 安裝時會做什麼（逐項列明——開發者一眼知範圍）
            $section_lines[] = "<div class='setup_review'>";

                $database_action_label = setup()->i18n()->get("section.upgrade_review.database_action");

                $section_lines[] = "<div class='setup_review-row'>";
                    $section_lines[] = "<span class='setup_review-label'>" . $database_action_label . "</span>";
                    $section_lines[] = "<span class='setup_review-value'>" . sc_html_encode($detected_text) . " → dbv-" . sc_html_encode($target_version) . "</span>";
                $section_lines[] = "</div>";

                $env_action_label = setup()->i18n()->get("section.upgrade_review.env_action");
                $env_action_text  = setup()->i18n()->get("section.upgrade_review.env_action_detail");

                $section_lines[] = "<div class='setup_review-row'>";
                    $section_lines[] = "<span class='setup_review-label'>" . $env_action_label . "</span>";
                    $section_lines[] = "<span class='setup_review-value'>" . $env_action_text . "</span>";
                $section_lines[] = "</div>";

                $files_action_label = setup()->i18n()->get("section.upgrade_review.files_action");
                $files_action_text  = setup()->i18n()->get("section.upgrade_review.files_action_detail");

                $section_lines[] = "<div class='setup_review-row'>";
                    $section_lines[] = "<span class='setup_review-label'>" . $files_action_label . "</span>";
                    $section_lines[] = "<span class='setup_review-value'>" . $files_action_text . "</span>";
                $section_lines[] = "</div>";

            $section_lines[] = "</div>";

            $backup_note_text = setup()->i18n()->get("section.upgrade_review.backup_note");
            $section_lines[] = "<p class='setup_review-note'>" . $backup_note_text . "</p>";

        $section_lines[] = "</div>";

        return $section_lines;
    }

    // 出本格底列（左＝空／右＝上一步·下一步）
    public function build_footer($section_nav)
    {
        $prev_section_id = $section_nav["prev_section_id"];
        $next_section_id = $section_nav["next_section_id"];

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-foot'>";
        $section_lines[] = "<div class='setup_section-foot__left'></div>";

        $section_lines[] = "<div class='setup_section-foot__right'>";

            $prev_step_text = setup()->i18n()->get("common.prev_step");
            $next_step_text = setup()->i18n()->get("common.next_step");

            $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev'>" . $prev_step_text . "</button>";

            // 判定：唔係最後一格 → 出下一步
            if ($section_nav["is_last"] !== true) {
                $section_lines[] = "<button type='button' class='button button--primary' data-setup_nav='" . $next_section_id . "' data-nav_action='next'>" . $next_step_text . "</button>";
            }

        $section_lines[] = "</div>";
        $section_lines[] = "</div>";

        return $section_lines;
    }
}
