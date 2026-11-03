<?php

namespace sc\setup\sections;

// == HKAI setup sections welcome view：welcome 格嘅 view 側（trait——掛入 welcome_Class；檔＝本格嘅 view）
// == 內容：三個 view 方法＝build_section／build_body／build_footer
// == 載入：welcome.php require_once（include once）

trait welcome_view
{
    // 出本格整件（head＋body＋footer——合成一個 array 交外殼；外殼只負責展示）
    public function build_section()
    {
        $section_nav = setup()->sections()->get_section_nav("welcome");

        $section_lines   = [];
        $section_head_text = setup()->i18n()->get("section.welcome.head");

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

    // 出本格 body（區塊＋置中 hero＋語言選擇——外殼直接嵌）
    public function build_body()
    {
        $setup_languages  = setup()->i18n()->get_languages();
        $current_language = setup()->i18n()->get_lang_code();
        $welcome_desc     = setup()->i18n()->get("welcome.desc");
        $welcome_desc     = htmlspecialchars($welcome_desc, ENT_QUOTES);

        $logo = sc_get_config("view.logo");
        $setup_logo_url  = setup()->url()->sc_to_web_path($logo);
        $product_version = setup()->get_version();

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-main setup_section-main--center'>";

        $section_lines[] = "<div class='setup_welcome'>";
        $section_lines[] = "<div class='check_hero'>";
        $section_lines[] = "<img class='check_hero__icon' src='" . $setup_logo_url . "' alt='HKAI'>";
        $section_lines[] = "<div class='check_hero__version'>" . $product_version . "</div>";
        $section_lines[] = "<p class='check_hero__desc'>" . $welcome_desc . "</p>";
        $section_lines[] = "<div class='check_hero__lang'>";

        // 逐個語言出選擇（次序＝顯示次序——label＋hidden radio：radio 值＝語言碼〔欄位契約·隨包袱交去〕；當前＝active＋預選）
        foreach ($setup_languages as $language_code => $language_name) {
            $active_class = "";
            $checked_attr = "";

            // 判定：語言＝當前碼 → 上 active＋預選
            if ($language_code === $current_language) {
                $active_class = " is-active";
                $checked_attr = " checked";
            }

            $section_lines[] = "<label class='setup_lang_button" . $active_class . "'>";
            $section_lines[] = "<input type='radio' name='formdata[welcome][language][value]' value='" . $language_code . "'" . $checked_attr . " hidden>";
            $section_lines[] = $language_name;
            $section_lines[] = "</label>";
        }

        $section_lines[] = "</div>";

        $section_lines[] = "</div>";

        // 安裝模式（全新安裝／升級搬遷——後續步驟按此調整）
        $source_mode_value = setup()->state()->get_setting_setup_process("welcome", "source_mode");
        $source_dir_value  = setup()->state()->get_setting_setup_process("welcome", "source_dir");

        $is_import_mode = ($source_mode_value === "import");

        $default_checked_attr = "checked='checked'";
        $import_checked_attr  = "";

        // 判定：上次揀咗搬遷 → 改由搬遷選項標記
        if ($is_import_mode === true) {
            $default_checked_attr = "";
            $import_checked_attr  = "checked='checked'";
        }

        $path_row_class = "setup_db_source-path is-hidden";

        // 判定：搬遷模式 → 目錄行顯示
        if ($is_import_mode === true) {
            $path_row_class = "setup_db_source-path";
        }

        $source_label_text  = setup()->i18n()->get("welcome.source_label");
        $mode_default_text  = setup()->i18n()->get("welcome.source_default");
        $mode_import_text   = setup()->i18n()->get("welcome.source_import");
        $import_placeholder = setup()->i18n()->get("welcome.source_import_placeholder");

        $empty_path_message = setup()->i18n()->get("check.migration_source.empty");

        $section_lines[] = "<div class='setup_db_source' data-msg_empty='" . sc_html_encode($empty_path_message) . "'>";
            $section_lines[] = "<div class='setup_db_source-title'>" . $source_label_text . "</div>";
            $section_lines[] = "<label class='setup_db_source-option'>";
                $section_lines[] = "<input type='radio' name='formdata[welcome][source_mode][value]' value='default' " . $default_checked_attr . "/>";
                $section_lines[] = "<span>" . $mode_default_text . "</span>";
            $section_lines[] = "</label>";
            $section_lines[] = "<label class='setup_db_source-option'>";
                $section_lines[] = "<input type='radio' name='formdata[welcome][source_mode][value]' value='import' " . $import_checked_attr . "/>";
                $section_lines[] = "<span>" . $mode_import_text . "</span>";
            $section_lines[] = "</label>";
            $section_lines[] = "<div class='" . $path_row_class . "'>";
                $section_lines[] = "<input type='text' class='setup_db_source-input' name='formdata[welcome][source_dir][value]' autocomplete='off' spellcheck='false' placeholder='" . $import_placeholder . "' value='" . sc_html_encode($source_dir_value) . "'/>";
                $section_lines[] = "<div class='setup_db_source-status'></div>";
            $section_lines[] = "</div>";
        $section_lines[] = "</div>";

        $section_lines[] = "</div>";
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

            $section_lines[] = "<button type='button' class='button' data-setup_nav='welcome' data-nav_action='refresh'>";
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
