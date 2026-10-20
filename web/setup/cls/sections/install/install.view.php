<?php

namespace sc\setup\sections;

// == HKAI setup sections install view：install 格 view 側（trait——掛入 install_Class；檔＝本格 view 側）
// == 內容：六個 view 方法＝build_section／build_body／build_footer／build_response_html／render_main_button／render_install_row
// == 載入：install.php require_once（include once）

trait install_view
{
    // 出本格整件（head＋body＋footer——合成一個 array 交外殼；外殼只負責展示）
    public function build_section()
    {
        $install_section = setup()->sections()->install();

        $section_nav = setup()->sections()->get_section_nav("install");

        $section_lines   = [];
        $section_head_text = setup()->i18n()->get("section.install.head");

        $section_lines[] = "<div class='setup_section-head'>" . $section_head_text . "</div>";

        $body_lines = $install_section->build_body();

        // 逐行入列（一行一動作——統一 $section_lines[] 形）
        foreach ($body_lines as $body_line_item) {
            $section_lines[] = $body_line_item;
        }

        $footer_lines = $install_section->build_footer($section_nav);

        // 逐行入列（同上）
        foreach ($footer_lines as $footer_line_item) {
            $section_lines[] = $footer_line_item;
        }

        return $section_lines;
    }

    // 出本格 body（引言＋逐個安裝步驟——各步自報初始狀態〔done／init〕）
    public function build_body()
    {
        $install_section = setup()->sections()->install();

        $section_lines = [];
        $section_lines[] = "<div class='setup_section-main'>";
            $section_intro_text = setup()->i18n()->get("section.install.intro");

            $section_lines[] = "<p class='check_intro'>" . $section_intro_text . "</p>";

            $install_steps = $install_section->get_install_steps();

            // 逐個安裝步驟（清單＝單一來源——次序＝執行次序）
            foreach ($install_steps as $install_step_item) {
                $install_section_id = $install_step_item["section_id"];
                $check_item_code    = $install_step_item["check_item_code"];

                $step_state = $install_section->get_install_step_state($install_section_id, $check_item_code);

                $section_lines[] = $install_section->render_install_row($install_step_item, $step_state);
            }

        $section_lines[] = "</div>";

        return $section_lines;
    }

    // 出本格底列（兩組——左＝上一步／右＝主掣〔安裝／完成〕；完成標記寫咗＝冇上一步）
    public function build_footer($section_nav)
    {
        $install_section = setup()->sections()->install();

        $setup_done = sc_get_config("setup.setup_done");

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-foot'>";
        $section_lines[] = "<div class='setup_section-foot__left'>";

            $prev_step_text = setup()->i18n()->get("common.prev_step");

            // 判定：完成標記未寫 → 出上一步（可返⑤之前嘅格）
            if (!is_file($setup_done)) {
                $prev_section_id = $section_nav["prev_section_id"];

                $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev'>" . $prev_step_text . "</button>";
            }

        $section_lines[] = "</div>";

        $section_lines[] = "<div class='setup_section-foot__right'>";
            $section_lines[] = $install_section->render_main_button();
        $section_lines[] = "</div>";
        $section_lines[] = "</div>";

        return $section_lines;
    }

    // 出本格回應 HTML（動作後重建——main＝body 行、footer＝底列行；位置本體在此，呼叫端零查找）
    public function build_response_html()
    {
        $install_section = setup()->sections()->install();

        $section_nav = setup()->sections()->get_section_nav("install");

        $response_html = [];
        $response_html["main"]   = $install_section->build_body();
        $response_html["footer"] = $install_section->build_footer($section_nav);

        return $response_html;
    }

    // 出主掣（未完成＝安裝掣〔動作指令·藍〕；完成標記已寫＝完成掣〔導覽——去⑥完成頁〕）
    public function render_main_button()
    {
        $setup_done = sc_get_config("setup.setup_done");

        $done_button_text    = setup()->i18n()->get("section.install.button_done");
        $install_button_text = setup()->i18n()->get("section.install.button_install");

        // 判定：完成標記已寫 → 完成掣（去⑥完成頁——同一 nav 制——交 switch_section；綠＝成功態）
        if (is_file($setup_done)) {
            return "<button type='button' class='button button--success' data-setup_nav='completed' data-nav_action='next'>" . $done_button_text . "</button>";
        }

        // 其餘（未寫）→ 安裝掣（動作指令——一撳＝全格一次過安裝；非導覽——錨＝data-action〔沿用 app 制〕）
        return "<button type='button' class='button button--primary' data-action='install'>" . $install_button_text . "</button>";
    }

    // 渲染安裝行（.check_item 框架 1:1——只加 data-install_status 閘門；狀態：init／loading／done／fail）
    public function render_install_row($install_step, $step_state)
    {
        $check_item_code = $install_step["check_item_code"];
        $step_label      = $install_step["label"];
        $step_value = $install_step["value"];

        $step_status = $step_state["status"];
        $step_detail = $step_state["detail"];

        // 狀態對照（init＝中性○；done＝pass ✓；fail＝fail ✕）
        $row_class = "";
        $mark_text = "○";

        // 判定：done → pass（✓）
        if ($step_status === "done") {
            $row_class = "pass";
            $mark_text = "✓";
        }

        // 判定：fail → fail（✕）
        if ($step_status === "fail") {
            $row_class = "fail";
            $mark_text = "✕";
        }

        $check_item_code_attr = htmlspecialchars($check_item_code, ENT_QUOTES);
        $step_status_attr     = htmlspecialchars($step_status, ENT_QUOTES);
        $install_section_id_attr = htmlspecialchars($install_step["section_id"], ENT_QUOTES);
        $step_label_html  = htmlspecialchars($step_label, ENT_QUOTES);
        $step_value_html  = htmlspecialchars($step_value, ENT_QUOTES);
        $step_detail_html = htmlspecialchars($step_detail, ENT_QUOTES);

        // 判定：有狀態 class → 附上（空＝純 check_item）
        $row_class_attr = "";

        // 判定：有狀態 class → 砌屬性字串
        if ($row_class !== "") {
            $row_class_attr = " " . $row_class;
        }

        $output_lines   = [];
        // 行屬性（一項一行——最後併成一個 tag；section_id＝行歸屬格——後端派發要用）
        $check_item_attrs   = [];
        $check_item_attrs[] = "class='check_item" . $row_class_attr . "'";
        $check_item_attrs[] = "data-section_id='" . $install_section_id_attr . "'";
        $check_item_attrs[] = "data-check_item_code='" . $check_item_code_attr . "'";
        $check_item_attrs[] = "data-install_status='" . $step_status_attr . "'";

        $output_lines[] = "<div " . implode(" ", $check_item_attrs) . ">";
        $output_lines[] = "<div class='mark'>" . $mark_text . "</div>";
        $output_lines[] = "<div class='cinfo'>";
        $output_lines[] = "<div class='cname'>" . $step_label_html . "</div>";

        // 判定：有值顯示 → 出（現值／遮罩／留空字眼——動作步空＝唔出）
        if ($step_value_html !== "") {
            $output_lines[] = "<div class='cvalue'>" . $step_value_html . "</div>";
        }

        // 判定：有詳情先出（空＝唔出·防空白行）
        if ($step_detail_html !== "") {
            $output_lines[] = "<div class='cdesc'>" . $step_detail_html . "</div>";
        }

        $output_lines[] = "</div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}
