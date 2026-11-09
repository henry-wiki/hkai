<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal\tabs;

// == sc_ai_projects modal get_modal_tabs：modal 域動作（trait——掛入 modal_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_projects/cls/modal/tabs/_.bridge.cls.php 分遷入（one file one action） ==

trait get_modal_tabs
{
    public function get_modal_tabs()
    {
        $modal_tabs = [];

        $modal_tabs[] = ["tab_id" => "general_form",   "label" => hkai()->i18n()->get("project.tab.general_form"),        "is_edit_only" => false, "archived_visibility" => "always"];
        $modal_tabs[] = ["tab_id" => "git",            "label" => "Git",              "is_edit_only" => true,  "archived_visibility" => "normal_only"];
        $modal_tabs[] = ["tab_id" => "rule",           "label" => hkai()->i18n()->get("project.tab.rule"),        "is_edit_only" => true,  "archived_visibility" => "normal_only"];
        $modal_tabs[] = ["tab_id" => "environment",    "label" => hkai()->i18n()->get("project.tab.environment"),         "is_edit_only" => true,  "archived_visibility" => "normal_only"];
        $modal_tabs[] = ["tab_id" => "diagnostics",    "label" => hkai()->i18n()->get("project.tab.diagnostics"), "is_edit_only" => true,  "archived_visibility" => "normal_only"];
        $modal_tabs[] = ["tab_id" => "delete_project", "label" => hkai()->i18n()->get("project.tab.delete"),      "is_edit_only" => true,  "archived_visibility" => "archive_only"];

        return $modal_tabs;
    }
}
