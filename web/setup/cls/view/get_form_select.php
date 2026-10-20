<?php

namespace sc\setup\view;

// == HKAI setup view get_form_select：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：砌下拉選單行（收整個 input 規格·規格要用嘅嘢本函數自取；選項列表＋標選中）

trait get_form_select
{
    private function get_form_select($item_input_spec, $input_section_id)
    {
        // 規格取值（收整個 spec——本函數自取要用嘅嘢）
        $input_name    = $item_input_spec["name"];
        $input_value   = $item_input_spec["value"];
        $input_options = $item_input_spec["options"];

        $input_msg = "";

        // 判定：有 msg → 攞文字（問題／提示字）
        if (isset($item_input_spec["msg"])) {
            $input_msg = $item_input_spec["msg"];
        }

        $control_lines   = [];
        $control_lines[] = "<div class='ccontrol'>";
        $control_lines[] = "<div class='input_row'>";

        // 欄位契約（統一）：name＝formdata[section_id][check_item_code][value]（有格 id 先組）——前端打包／後台查表一律靠佢
        $input_field_name = $input_name;

        // 判定：有格 id → 用完整契約名
        if ($input_section_id !== "") {
            $input_field_name = "formdata[" . $input_section_id . "][" . $input_name . "][value]";
        }

        $input_msg_html = htmlspecialchars($input_msg, ENT_QUOTES);

        // 選單屬性（一項一行——最後併成一個 tag）
        $select_attrs   = [];
        $select_attrs[] = "class='input_field'";
        $select_attrs[] = "name='" . $input_field_name . "'";

        $control_lines[] = "<select " . implode(" ", $select_attrs) . ">";

        // 逐個選項出 option 行（現值＝選中；字串＝value／label 同體；陣列＝分開〔空值佔位〕）
        foreach ($input_options as $input_option_item) {
            $input_option_value = $input_option_item;
            $input_option_label = $input_option_item;

            // 判定：選項係陣列（value／label 對）→ 分開攞
            if (is_array($input_option_item)) {
                $input_option_value = $input_option_item["value"];
                $input_option_label = $input_option_item["label"];
            }

            $input_option_attr       = htmlspecialchars($input_option_value, ENT_QUOTES);
            $input_option_label_html = htmlspecialchars($input_option_label, ENT_QUOTES);

            // 選項屬性（一項一行——最後併成一個 tag）
            $option_attrs   = [];
            $option_attrs[] = "value='" . $input_option_attr . "'";

            // 判定：同現值同 → 加 selected
            if ($input_option_value === $input_value) {
                $option_attrs[] = "selected";
            }

            $control_lines[] = "<option " . implode(" ", $option_attrs) . ">" . $input_option_label_html . "</option>";
        }

        $control_lines[] = "</select>";
        $control_lines[] = "</div>";
        $control_lines[] = "<div class='input_msg'>" . $input_msg_html . "</div>";
        $control_lines[] = "</div>";

        return $control_lines;
    }
}
