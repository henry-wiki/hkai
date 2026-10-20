<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_textarea_row：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/view.php 分遷入（one file one action） ==

trait build_textarea_row
{
    public function build_textarea_row($label_text, $field_key, $field_value, $options = [])
    {
        $placeholder = isset($options["placeholder"]) ? $options["placeholder"] : "";
        $subtitle    = isset($options["subtitle"]) ? $options["subtitle"] : "";
        
        $control_lines   = [];
        $control_lines[] = "<textarea class='modal__input form_textarea' data-field='" . $field_key . "' name='formdata[" . $field_key . "]' placeholder='" . $placeholder . "'>" . sc_html_encode($field_value, ENT_QUOTES) . "</textarea>";
        
        return $this->build_form_row($label_text, $subtitle, implode("\r\n", $control_lines));
    }
}
