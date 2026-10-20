<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_field_row：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/view.php 分遷入（one file one action） ==

trait build_field_row
{
    public function build_field_row($field_key, $field_value, $options = [])
    {
        $input_type  = isset($options["type"]) ? $options["type"] : "text";
        $placeholder = isset($options["placeholder"]) ? $options["placeholder"] : "";
        
        $field_value_encoded = sc_html_encode($field_value, ENT_QUOTES);
        
        $input_attr   = [];
        $input_attr[] = "type='{$input_type}'";
        $input_attr[] = "class='modal__input'";
        $input_attr[] = "data-field='{$field_key}'";
        $input_attr[] = "name='formdata[{$field_key}]'";
        $input_attr[] = "value='{$field_value_encoded}'";
        $input_attr[] = "placeholder='{$placeholder}'";
        $input_attr[] = "autocomplete='off'";
        
        $input_attr = implode(" ", $input_attr);
        $control_lines   = [];
        $control_lines[] = "<input {$input_attr}/>";
        
        return implode("\r\n", $control_lines);
    }
}
