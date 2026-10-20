<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\view;

// == sc_ai_chats chat render_chat_sidebar：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/view.php 分獨立檔（one file one function） ==

trait render_chat_sidebar
{

    // 對話清單面板（外框＋header＋空容器）——未選專案時顯示共用空狀態
    public function render_chat_sidebar()
    {
        // 未選專案才有空狀態（已選專案 → 隱藏，等 JS 載入清單）
        $empty_state_hidden = sc_ai_chats()->chat()->is_project_selected();
        
        // header kicker＝目前專案名（未選／查無＝空；跟切換由 chat_list_fetch 回應更新）
        $selected_project_slug = sc_ai_projects()->project()->get_selected_project_slug();
        
        $project_name = "";
        // 判定：有選專案 → 查嗰行攞名
        if ((string)$selected_project_slug !== "") {
            $project_row = sc_ai_projects()->project()->get_project_row($selected_project_slug);
            
            // 判定：查得到 → 用個名
            if ($project_row !== null) {
                $project_name = $project_row["project_name"];
            }
        }
        
        // 側欄寬（活動 sidebar_state 讀——隨 SSR 落 CSS var；未存過＝唔落〔食 CSS 預設 260px〕）
        $sidebar_state      = hkai__sc_page_activity()->get_activity("sidebar_state");
        $chats_width        = $sidebar_state["chats_width"] ?? "";

        $sidebar_width_attr = "";

        // 判定：有存過欄寬 → 落 inline CSS var
        if ($chats_width !== "") {
            $sidebar_width_attr = " style='--sc_sidebar_w:{$chats_width}px'";
        }

        $outputLines   = [];
        $outputLines[] = "<aside class='sc_sidebar sc_sidebar--chats'{$sidebar_width_attr}>";
            $outputLines[] = "<div class='sc_panel_header'>";
                $outputLines[] = "<div class='sc_panel_header__label'>";
                    $outputLines[] = "<div class='sc_panel_header__kicker'>" . sc_html_encode($project_name) . "</div>";
                    $outputLines[] = "<span class='sc_panel_header__title'>對話 <span>Chats</span></span>";
                $outputLines[] = "</div>";
                $outputLines[] = "<div class='sc_panel_header__actions'>";
                    $outputLines[] = "<button class='sc_icon_btn' title='搜尋'>⌕</button>";
                    $outputLines[] = "<button class='sc_icon_btn' data-btn='chat_add' title='新增對話'>＋</button>";
                $outputLines[] = "</div>";
            $outputLines[] = "</div>";
            $outputLines[] = "<div class='sc_chat_list' data-loaded='0'>";
                $outputLines[] = sc_empty_state(hkai__i18n()->get("empty.select_project"), $empty_state_hidden);
            $outputLines[] = "</div>";
            // 拖拉調寬把手（貼右邊界——sc_sidebar.js 綁定拖曳；200–300px；中段 grip＝拖曳舒適位）
            $outputLines[] = "<div class='sc_sidebar__resize_line' data-resize='chats' title='拖拉調整對話欄寬度'>";
                $outputLines[] = "<span class='sc_sidebar__resize_grip' aria-hidden='true'></span>";
            $outputLines[] = "</div>";
            // 欄底 status bar（sc_ai_status_bar plugin 產生——chat region；嵌喺 panel 內，唔係 fixed footer）
            $outputLines[] = sc_ai_status_bar()->status_bar()->render_region("chat");
        $outputLines[] = "</aside>";
        
        return implode("\r\n", $outputLines);
    }

}
