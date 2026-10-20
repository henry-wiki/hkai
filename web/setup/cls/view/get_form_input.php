<?php

namespace sc\setup\view;

// == HKAI setup view get_form_input：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：砌輸入控件行（文字格——收整個 input 規格·規格要用嘅嘢本函數自取；msg_class＝訊息著色）

trait get_form_input
{
    private function get_form_input($item_input_spec, $input_section_id)
    {
        // 規格取值（收整個 spec——本函數自取要用嘅嘢）
        $input_name = $item_input_spec["name"];
        $input_value = $item_input_spec["value"];

        $input_msg         = "";
        $input_disabled    = false;
        $input_placeholder = "";
        $input_readonly    = false;
        $input_msg_class   = "";

        // 判定：有 msg → 攞文字（問題／提示字）
        if (isset($item_input_spec["msg"])) {
            $input_msg = $item_input_spec["msg"];
        }

        // 判定：有 disabled → 攞落嚟（不准改）
        if (isset($item_input_spec["disabled"])) {
            $input_disabled = $item_input_spec["disabled"];
        }

        // 判定：有 placeholder → 攞落嚟（輸入格示例字）
        if (isset($item_input_spec["placeholder"])) {
            $input_placeholder = $item_input_spec["placeholder"];
        }

        // 判定：有 readonly → 攞落嚟（唯讀——唔准編）
        if (isset($item_input_spec["readonly"])) {
            $input_readonly = $item_input_spec["readonly"];
        }

        // 判定：有 msg_class → 攞落嚟（訊息著色）
        if (isset($item_input_spec["msg_class"])) {
            $input_msg_class = $item_input_spec["msg_class"];
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

        $input_value_attr = htmlspecialchars($input_value, ENT_QUOTES);

        $input_msg_class_attr = "";

        // 判定：有 msg_class → 加（訊息著色——例 is-warn）
        if ($input_msg_class !== "") {
            $input_msg_class_attr = " " . $input_msg_class;
        }

        // 示例字（檢查函數帶入優先——未帶用預設）
        $input_placeholder_text = setup()->i18n()->get("form.input.default_placeholder");

        // 判定：有帶 placeholder → 換上（HTML 轉義）
        if ($input_placeholder !== "") {
            $input_placeholder_text = htmlspecialchars($input_placeholder, ENT_QUOTES);
        }

        // 輸入格屬性（一項一行——最後併成一個 tag）
        $input_attrs   = [];
        $input_attrs[] = "type='text'";
        $input_attrs[] = "autocomplete='off'";
        $input_attrs[] = "spellcheck='false'";
        $input_attrs[] = "class='input_field'";
        $input_attrs[] = "name='" . $input_field_name . "'";
        $input_attrs[] = "value='" . $input_value_attr . "'";

        // 判定：唔准改 → 加 disabled
        if ($input_disabled === true) {
            $input_attrs[] = "disabled";
        }

        // 判定：唯讀（顯示值——唔准編）→ 加 readonly
        if ($input_readonly === true) {
            $input_attrs[] = "readonly";
        }

        $input_attrs[] = "placeholder='" . $input_placeholder_text . "'";

        $control_lines[] = "<input " . implode(" ", $input_attrs) . " />";
        $control_lines[] = "</div>";
        $control_lines[] = "<div class='input_msg" . $input_msg_class_attr . "'>" . $input_msg_html . "</div>";
        $control_lines[] = "</div>";

        return $control_lines;
    }
}
