<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\view;

// == sc_ai_chats chat render_chat_list：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/view.php 分獨立檔（one file one function） ==

trait render_chat_list
{

    // 渲染對話列表 HTML（目前選取的對話加上高亮；結構：sc_chat_card > sc_chat_card__link ＋ 歸檔掣同層；資訊＝標題／時間／模型／訊息數）
    // 排序：更新時間新到舊（由 repo_select 的 ORDER BY chat_updated_at DESC, chat_id DESC 決定——顯示順序 SSOT 在資料層）
    public function render_chat_list($selected_project_slug, $selected_chat_sid = null)
    {
        // $this 型別提示（intelephense 跨 trait F12 用——trait 自身唔識組裝佢所屬嘅 class）
        /** @var \sc\app\hkai\plugins\sc_ai_chats\chat\chat_Class $this */

        // 資料來源經 repo：先依 slug 取專案列 → 再依 project_id 搜尋該專案下的對話列
        $project_row = sc_ai_projects()->project()->get_project_row($selected_project_slug);

        // home 語境（空 slug／home 段）→ 虛擬專案列（project_id 0）
        if ($project_row === null && ($selected_project_slug === "" || $selected_project_slug === "home")) {
            $project_row = [
                "project_id"     => 0,
                "project_slug"   => "home",
                "project_status" => "normal",
                "project_name"   => "",
            ];
        }

        $chat_rows = [];
        if ($project_row !== null) {
            $chat_rows = sc_ai_chats()->chat()->get_chat_rows($project_row["project_id"]);
        }
        
        $chat_rows = sc_html_encode($chat_rows);
        
        $content = [];
        
        // 逐對話輸出列表項目（單項渲染共用 render_chat_item——清單與單項同一渲染源）
        foreach ($chat_rows as $chat_row_item) {
            $content[] = $this->render_chat_item($chat_row_item, $project_row, $selected_chat_sid);
        }
        
        return implode("\r\n", $content);
    }

}
