<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_section_heading：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/view.php 分遷入（one file one action） ==

trait build_section_heading
{
    public function build_section_heading($section_title, $section_description)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='project-section__heading'>";
            $outputLines[] = "<div class='project-section__title'>" . $section_title . "</div>";
            $outputLines[] = "<div class='project-section__description'>" . $section_description . "</div>";
        $outputLines[] = "</div>";
        
        return implode("\r\n", $outputLines);
    }
}
