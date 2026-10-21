<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\git\status;

// == sc_ai_diagnostics git get_git_status_item：git 域動作（trait——掛入 git_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat git_Class 分遷入（one file one action） ==

trait get_git_status_item
{
    // 提供 status item HTML 給 sc_ai_status_bar（Closure 時求值——provider 全權決定 item 內容；只決定擺去邊）
    // 文字槽 sc-text 隨 health 報告 git 每輪更新（此處只出外框——⎇ —）
    public function get_git_status_item()
    {
        $project_slug = sc_ai_projects()->project()->get_selected_project_slug();
        $project_row  = sc_ai_projects()->project()->get_project_row($project_slug);
        
        // 無選取專案 → 不顯示
        if ($project_row === null) {
            return null;
        }

        // 判定：Home 專案（slug＝home）→ 唔顯示 git 狀態（Home 冇 repo 概念）
        if ($project_row["project_slug"] === "home") {
            return null;
        }
        
        $status_item_html   = [];
        $status_item_html[] = "<div class='sc_statusbar__item sc_statusbar__item--clickable sc_statusbar__item--git' data-click='project_git'>";
            $status_item_html[] = "<span class='sc-icon'><i class='fa-solid fa-code-merge'></i></span>";
            $status_item_html[] = "<span class='sc-text'></span>";
        $status_item_html[] = "</div>"; 
        
        return implode("", $status_item_html);
    }
}
