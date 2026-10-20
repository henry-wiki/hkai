<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\view;

// == sc_ai_chats chat render_archived_chat_list：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-10-04 歸檔清單（archive chat list）——/<project_slug>/archive/ 專用；卡片共用 render_chat_item ==

trait render_archived_chat_list
{

    // 渲染歸檔對話清單 HTML（內容＝該專案下已歸檔嘅對話；清單與單項同一渲染源；瀏覽中嘅歸檔對話加高亮）
    public function render_archived_chat_list($selected_project_slug, $selected_chat_sid = null)
    {
        // 資料來源經 repo：先依 slug 取專案列 → 再依 project_id 搜尋該專案下已歸檔嘅對話列
        $project_row = sc_ai_projects()->project()->get_project_row($selected_project_slug);
        $chat_rows = [];
        if ($project_row !== null) {
            $chat_rows = sc_ai_chats()->chat()->get_archived_chat_rows($project_row["project_id"]);
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
