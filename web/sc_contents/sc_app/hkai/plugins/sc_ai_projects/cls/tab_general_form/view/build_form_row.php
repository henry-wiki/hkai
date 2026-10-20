<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_form_row：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/view.php 分遷入（one file one action） ==

trait build_form_row
{
    public function build_form_row($label_text, $description_text, $control_html, $required = false)
    {
        $required_mark = "";
        if ($required) {
            $required_mark = "<span class='form_required'>*</span>";
        }
        
        $outputLines   = [];
        $outputLines[] = "<div class='modal-form__row form_row'>";
            $outputLines[] = "<div class='modal-form__icon'></div>";
            $outputLines[] = "<div class='modal-form__info'>";
                $outputLines[] = "<div class='modal-form__label'>" . $label_text . $required_mark . "</div>";
                $outputLines[] = "<div class='modal-form__desc'>" . $description_text . "</div>";
            $outputLines[] = "</div>";
            $outputLines[] = "<div class='modal-form__content'>";
                $outputLines[] = $control_html;
                $outputLines[] = "<div class='form_err'></div>";
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";
        
        return implode("\r\n", $outputLines);
    }
}
