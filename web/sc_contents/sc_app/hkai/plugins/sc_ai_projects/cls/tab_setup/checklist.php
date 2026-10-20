<?php

// == sc_ai_projects 檢查清單「定義」（非 class——由同域 checks_Class require 後取用） ==
// == 每項：label（顯示文字）／group（分組標題）／scope（system＝與專案無關，只檢查；project＝與專案有關，可操作）／hint（未通過說明）／check（setup\checks_Class 檢查方法名） ==
// == 改清單只動本檔；檢查實作在同域 checks_Class（逐項由 checks_Class::run_checklist 派發） ==

return [
    "git_installed" => [
        "label"  => "Git 已安裝",
        "group"  => "系統檢查（與專案無關）",
        "scope"  => "system",
        "hint"   => "本機需安裝 Git 才能初始化與提交",
        "check"  => "check_git_installed",
    ],
    "path_exists" => [
        "label"  => "專案路徑存在",
        "group"  => "系統檢查（與專案無關）",
        "scope"  => "system",
        "hint"   => "確認本機的專案路徑是否正確",
        "check"  => "check_path_exists",
    ],
    "git_repo" => [
        "label"  => "專案已初始化 Git",
        "group"  => "專案檢查",
        "scope"  => "project",
        "hint"   => "尚未偵測到 .git。可在此專案路徑執行 git init。",
        "check"  => "check_git_repo",
    ],
    "has_commit" => [
        "label"  => "已有第一次提交",
        "group"  => "專案檢查",
        "scope"  => "project",
        "hint"   => "儲存庫尚無任何提交。",
        "check"  => "check_has_commit",
    ],
    "has_gitignore" => [
        "label"  => "有 .gitignore",
        "group"  => "專案檢查",
        "scope"  => "project",
        "hint"   => "專案根目錄缺少 .gitignore。",
        "check"  => "check_has_gitignore",
    ],
    "has_remote" => [
        "label"  => "已設定遠端 origin",
        "group"  => "專案檢查",
        "scope"  => "project",
        "hint"   => "尚未設定 origin 遠端。",
        "check"  => "check_has_remote",
    ],
    "has_description" => [
        "label"  => "已填描述",
        "group"  => "專案檢查",
        "scope"  => "project",
        "hint"   => "在表單填寫專案描述。",
        "check"  => "check_has_description",
    ],
];
