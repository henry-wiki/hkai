<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project render_project_sidebar：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_view.php 分遷入（one file one action） ==

trait render_project_sidebar
{
    public function render_project_sidebar()
    {
        // 側欄寬（活動 sidebar_state 讀——隨 SSR 落 CSS var；未存過＝唔落〔食 CSS 預設 220px〕）
        $sidebar_state      = hkai__sc_page_activity()->get_activity("sidebar_state");
        $projects_width     = $sidebar_state["projects_width"] ?? "";

        $sidebar_width_attr = "";

        // 判定：有存過欄寬 → 落 inline CSS var
        if ($projects_width !== "") {
            $sidebar_width_attr = " style='--sc_sidebar_w:{$projects_width}px'";
        }

        $output_lines   = [];
        $output_lines[] = "<aside class='sc_sidebar sc_sidebar--projects'{$sidebar_width_attr}>";
            $output_lines[] = "<div class='sc_panel_header'>";
                $output_lines[] = "<div class='sc_panel_header__label'>";
                    $output_lines[] = "<div class='sc_panel_header__kicker'>Workspace</div>";
                    $output_lines[] = "<a class='sc_panel_header__title' href='/'>專案 <span>Projects</span></a>";
                $output_lines[] = "</div>";
                $output_lines[] = "<div class='sc_panel_header__actions'>";
                    $output_lines[] = "<button class='sc_icon_btn' data-btn='modal_open_add' title='新增專案'>+</button>";
                $output_lines[] = "</div>";
            $output_lines[] = "</div>";
            $output_lines[] = "<div class='sc_project_list' data-loaded='0'></div>";
            // 拖拉調寬把手（貼右邊界——sc_sidebar.js 綁定拖曳；200–300px；中段 grip＝拖曳舒適位）
            $output_lines[] = "<div class='sc_sidebar__resize_line' data-resize='projects' title='拖拉調整專案欄寬度'>";
                $output_lines[] = "<span class='sc_sidebar__resize_grip' aria-hidden='true'></span>";
            $output_lines[] = "</div>";
            // 欄底 status bar（sc_ai_status_bar plugin 產生——project region；嵌喺 panel 內，唔係 fixed footer）
            $output_lines[] = sc_ai_status_bar()->status_bar()->render_region("project");
        $output_lines[] = "</aside>";
        
        return implode("\r\n", $output_lines);
    }
}
