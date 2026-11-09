<?php

// == sc_ai_projects 檢查清單「定義」（非 class——由同域 checks_Class require 後取用） ==
// == 每項：label（顯示文字）／group（分組標題）／scope（system＝與專案無關，只檢查；project＝與專案有關，可操作）／hint（未通過說明）／check（setup\checks_Class 檢查方法名） ==
// == 改清單只動本檔；檢查實作在同域 checks_Class（逐項由 checks_Class::run_checklist 派發） ==

return [
    "git_installed" => [
        "label"  => hkai()->i18n()->get("project.checklist.git_installed"),
        "group"  => hkai()->i18n()->get("project.checklist.group_system"),
        "scope"  => "system",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_git_installed"),
        "check"  => "check_git_installed",
    ],
    "path_exists" => [
        "label"  => hkai()->i18n()->get("project.checklist.path_exists"),
        "group"  => hkai()->i18n()->get("project.checklist.group_system"),
        "scope"  => "system",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_path_exists"),
        "check"  => "check_path_exists",
    ],
    "git_repo" => [
        "label"  => hkai()->i18n()->get("project.checklist.git_repo"),
        "group"  => hkai()->i18n()->get("project.checklist.group_project"),
        "scope"  => "project",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_git_repo"),
        "check"  => "check_git_repo",
    ],
    "has_commit" => [
        "label"  => hkai()->i18n()->get("project.checklist.has_commit"),
        "group"  => hkai()->i18n()->get("project.checklist.group_project"),
        "scope"  => "project",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_has_commit"),
        "check"  => "check_has_commit",
    ],
    "has_gitignore" => [
        "label"  => hkai()->i18n()->get("project.checklist.has_gitignore"),
        "group"  => hkai()->i18n()->get("project.checklist.group_project"),
        "scope"  => "project",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_has_gitignore"),
        "check"  => "check_has_gitignore",
    ],
    "has_remote" => [
        "label"  => hkai()->i18n()->get("project.checklist.has_remote"),
        "group"  => hkai()->i18n()->get("project.checklist.group_project"),
        "scope"  => "project",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_has_remote"),
        "check"  => "check_has_remote",
    ],
    "has_description" => [
        "label"  => hkai()->i18n()->get("project.checklist.has_description"),
        "group"  => hkai()->i18n()->get("project.checklist.group_project"),
        "scope"  => "project",
        "hint"   => hkai()->i18n()->get("project.checklist.hint_has_description"),
        "check"  => "check_has_description",
    ],
];
