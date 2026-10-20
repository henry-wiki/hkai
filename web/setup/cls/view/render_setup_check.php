<?php

namespace sc\setup\view;

// == HKAI setup view render_setup_check：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：卡片渲染（.check_item 樣式——同 sc_ai_projects 檢查卡共用類；✓／✕／△＋名稱＋說明）

trait render_setup_check
{
    public function render_setup_check($item_result_item, $section_id = "", $check_item_code = "")
    {
        $item_status = $item_result_item["status"];
        
        // 顯示狀態（URL force_result＝測試覆寫；檢查實況唔變——輸出嗰刻先換 layout）
        $display_status = $item_status;
        
        // 判定：URL 帶 force_result → 值先過三態閘
        if (isset($_GET["force_result"])) {
            $force_status = $_GET["force_result"];
            
            // 判定：值喺三態表（pass／warn／fail）→ 顯示改用嗰個（唔合法＝照用實況）
            if (in_array($force_status, ["pass", "warn", "fail"])) {
                $display_status = $force_status;
            }
        }
        
        $check_cssClass = [];
        $check_cssClass["pass"] = "pass";
        $check_cssClass["fail"] = "fail";
        $check_cssClass["warn"] = "warn";
        
        $check_mark_text = [];
        $check_mark_text["pass"] = "✓";
        $check_mark_text["fail"] = "✕";
        $check_mark_text["warn"] = "△";
        
        // 樣式與符號（default fail——唔喺表：一律當 fail，唔會誤過關）
        $item_class = $check_cssClass["fail"];
        $mark_text  = $check_mark_text["fail"];
        
        // 判定：顯示狀態喺表（pass／warn／fail——SSOT 三態）→ 查表上樣式
        if (isset($check_cssClass[$display_status])) {
            $item_class = $check_cssClass[$display_status];
            $mark_text  = $check_mark_text[$display_status];
        }
        
        // 顯示內容（result_content 嗰格——實況／覆寫一律由嗰格出）
        $display_detail = "";
        $display_hint   = "";
        
        // 判定：嗰格有 detail → 攞
        if (isset($item_result_item["result_content"][$display_status]["detail"])) {
            $display_detail = $item_result_item["result_content"][$display_status]["detail"];
        }
        
        // 判定：嗰格有 hint → 攞
        if (isset($item_result_item["result_content"][$display_status]["hint"])) {
            $display_hint = $item_result_item["result_content"][$display_status]["hint"];
        }
        
        // 修正行（唔係 pass——fail／warn／未知當 fail 都要出）
        $show_hint = false;
        
        // 判定：顯示狀態唔係通過 → 要出
        if ($display_status !== "pass") {
            $show_hint = true;
        }
        
        $item_label  = htmlspecialchars($item_result_item["label"], ENT_QUOTES);
        $item_detail = htmlspecialchars($display_detail, ENT_QUOTES);
        $item_hint   = htmlspecialchars($display_hint, ENT_QUOTES);
        
        // 判定：detail 帶 http(s) 連結文字 → 轉可撳連結（開新窗；其餘照文字）
        if (preg_match("#https?://#", $item_detail)) {
            $item_detail = preg_replace(
                "#(https?://[^\s<>'\"]+)#",
                "<a href='$1' target='_blank' rel='noopener'>$1</a>",
                $item_detail
            );
        }
        
        // 判定：hint 空 → 唔出（防空白行）
        if ($item_hint === "") {
            $show_hint = false;
        }
        
        // 輸入組規格（一卡一行——有先出；name／value／options；例：Python 執行檔路徑——手動貼）
        $item_input_specs = [];

        // 判定：有 input 規格 → 轉單行表
        if (isset($item_result_item["input"])) {
            $item_input_specs = [$item_result_item["input"]];
        }
        

        // 項目附註（note——唔影響狀態；有＝右中出一盞燈·hover 出字）
        $item_note = "";
        
        // 判定：有 note → 攞文字
        if (isset($item_result_item["note"])) {
            $item_note = $item_result_item["note"];
        }
        
        $item_note_attr = htmlspecialchars($item_note, ENT_QUOTES);
        
        $item_status_attr     = htmlspecialchars($display_status, ENT_QUOTES);
        $check_item_code_attr = htmlspecialchars($check_item_code, ENT_QUOTES);

        $output_lines   = [];
        $output_lines[] = "<div class='check_item " . $item_class . "' data-status='" . $item_status_attr . "' data-check_item='" . $check_item_code_attr . "'>";
        $output_lines[] = "<div class='mark'>" . $mark_text . "</div>";
        $output_lines[] = "<div class='cinfo'>";
            $output_lines[] = "<div class='cname'>" . $item_label . "</div>";
            
            // 判定：有 detail 先出（空＝唔出·防空白行）
            if ($item_detail !== "") {
                $output_lines[] = "<div class='cdesc'>" . $item_detail . "</div>";
            }
            
            // 判定：要出修正行 → 加一行
            if ($show_hint) {
                $fix_prefix_text = setup()->i18n()->get("common.fix_prefix");

                $output_lines[] = "<div class='cdesc'>" . $fix_prefix_text . $item_hint . "</div>";
            }
            
            // 逐行出輸入組（每行＝一 ccontrol；規格整份交落去——要用嘅嘢函數自取）
            foreach ($item_input_specs as $item_input_spec) {
                $item_input_options = [];

                // 判定：有 options → 攞落嚟（下拉選單判定用）
                if (isset($item_input_spec["options"])) {
                    $item_input_options = $item_input_spec["options"];
                }

                $item_input_control_lines = [];

                // 判定：有 options → 出下拉選單
                if (count($item_input_options) !== 0) {
                    $item_input_control_lines = setup()->view()->get_form_select($item_input_spec, $section_id);
                }

                // 其餘 → 出文字格
                else {
                    $item_input_control_lines = setup()->view()->get_form_input($item_input_spec, $section_id);
                }

                // 逐行入列（一行一動作——統一 $output_lines[] 形）
                foreach ($item_input_control_lines as $item_input_control_line_item) {
                    $output_lines[] = $item_input_control_line_item;
                }
            }

        $output_lines[] = "</div>";
        
        // 判定：有 note → 出燈（項目右中——hover 出字）
        if ($item_note !== "") {
            $output_lines[] = "<div class='note_light' title='" . $item_note_attr . "'>💡</div>";
        }
        
        $output_lines[] = "</div>";
        
        return implode("\r\n", $output_lines);
    }
}
