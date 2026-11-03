<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\view;

// == sc_ai_chats chat get_chat_item_html：單一對話卡 HTML（trait——掛入 chat_Class；檔＝動作）
// == 職責：chat_sid＋專案段 → 對話列＋專案列 → render 卡片 HTML（唔經 HTTP；health_stream 事件直接帶走）
// == 上游：sc_stream state 線（chat 變動事件）；下游：get_chat_row_by_sid()、render_chat_item()

trait get_chat_item_html
{
    // 取單一對話卡 HTML（查唔到＝null；$selected_chat_sid＝當前頁對話段，用嚟標 active）
    public function get_chat_item_html($chat_sid, $project_slug, $selected_chat_sid)
    {
        // 查表用純 sid（卡片身份＝段形——歸檔項帶 archive@ 前綴）
        $resolved_chat  = $this->resolve_chat_sid($chat_sid);
        $lookup_chat_sid = $resolved_chat["chat_sid"];

        $chat_row = $this->get_chat_row_by_sid($lookup_chat_sid);

        // 判定：對話唔存在 → 無 HTML
        if ($chat_row === null) {
            return null;
        }

        // 轉義一次過（整個列交 sc_html_encode——同清單渲染一致）
        $chat_row_item = sc_html_encode($chat_row);

        // 專案段查表用純 slug（歸檔形 archive@<project_slug> → @ 後）
        $resolved_project_slug = sc_ai_projects()->project()->resolve_project_slug($project_slug);
        $lookup_project_slug   = $resolved_project_slug["project_slug"];

        $project_row = sc_ai_projects()->project()->get_project_row($lookup_project_slug);

        // 判定：專案唔存在 → 連結第一段無所依，無 HTML
        if ($project_row === null) {
            return null;
        }

        return $this->render_chat_item($chat_row_item, $project_row, $selected_chat_sid);
    }
}
