<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\render;

// == sc_ai_status_bar render render_chat_region：對話欄（trait——掛入 status_bar_Class；檔＝一個 region）
// == 職責：組 .sc_statusbar--chat（💬 當前對話標題＋指示器槽）——只認「選了哪個對話」
// == 上游：sc_ai_chats chat/view/render_chat_sidebar.php；下游：chats 域（sid、對話列）

trait render_chat_region
{
    // 對話欄 HTML（步驟：① 取對話段 ② 對話段→純 sid ③ 對話標題 ④ 輸出）
    public function render_chat_region()
    {
        // ① 當前對話段（頁面＝URL／request＝payload）
        $chat_sid = sc_ai_chats()->chat()->get_chat_sid();

        // ② 對話段 → 純 sid
        $resolved_chat   = sc_ai_chats()->chat()->resolve_chat_sid($chat_sid);
        $lookup_chat_sid = $resolved_chat["chat_sid"];

        // ③ 對話標題（查唔到＝未選對話）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($lookup_chat_sid);

        $chat_title = hkai()->i18n()->get("status_bar.no_chat");

        // 判定：查到對話列 → 用對話標題
        if ($chat_row !== null) {
            $chat_title = $chat_row["chat_title"];
        }

        // ④ 歸檔入口（只喺已選專案時出——入／離歸檔兩向都由後端砌；前端只揀方向）
        $archive_line = "";

        $project_slug = sc_ai_projects()->project()->get_project_slug();

        // 判定：有專案語境 → 砌歸檔入口
        if ((string)$project_slug !== "") {
            $resolved_project_slug = sc_ai_projects()->project()->resolve_project_slug($project_slug);
            $project_segment       = $resolved_project_slug["project_slug"];

            $is_archive = sc_ai_chats()->chat()->is_archive_chat_segment($chat_sid);

            // 判定：專案本身係歸檔語境 → 一律當歸檔
            if ($resolved_project_slug["archive"] === 1) {
                $is_archive = true;
            }

            $archive_href_enter  = "/" . $project_segment . "/archive/";
            $archive_href_leave  = "/" . $project_segment . "/";
            $archive_title_enter = hkai()->i18n()->get("chat.archive");
            $archive_title_leave = hkai()->i18n()->get("chat.archive_leave");
            $archive_icon_enter  = "fa-solid fa-box-archive";
            $archive_icon_leave  = "fa-solid fa-arrow-left";

            $archive_href  = $archive_href_enter;
            $archive_title = $archive_title_enter;
            $archive_icon  = $archive_icon_enter;

            // 判定：身處歸檔語境 → 顯示離開歸檔
            if ($is_archive === true) {
                $archive_href  = $archive_href_leave;
                $archive_title = $archive_title_leave;
                $archive_icon  = $archive_icon_leave;
            }

            $archive_line = "<a class='sc_statusbar__archive' href='{$archive_href}' title='{$archive_title}' data-href_enter='{$archive_href_enter}' data-href_leave='{$archive_href_leave}' data-title_enter='{$archive_title_enter}' data-title_leave='{$archive_title_leave}' data-icon_enter='{$archive_icon_enter}' data-icon_leave='{$archive_icon_leave}'><i class='{$archive_icon}'></i></a>";
        }

        // ⑤ 輸出
        $output_lines   = [];
        $output_lines[] = "<div class='sc_statusbar sc_statusbar--chat'>";
        $output_lines[] = "<div class='sc_statusbar__item sc_statusbar__item--chat'>💬 <span class='sc-text'>" . $chat_title . "</span></div>";

        // 判定：有歸檔入口 → 輸出（無＝唔出空行）
        if ($archive_line !== "") {
            $output_lines[] = $archive_line;
        }

        $output_lines[] = "<div class='sc_statusbar__indicators' data-region='chat'></div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}
