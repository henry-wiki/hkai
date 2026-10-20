<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project render_project_list：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_view.php 分遷入（one file one action） ==

trait render_project_list
{
    public function render_project_list($selected_project_slug = null)
    {
        // 資料來源經 repo（同 sc_ai_chats_view 的 pattern——唔經 main 快取）
        $projects   = sc_ai_projects()->project()->get_project_rows();
        $projects   = sc_html_encode($projects);
        
        // 判定：一個專案都冇 → 共用空狀態（請建立專案——引導用戶行第一步）
        if (count($projects) === 0) {
            return sc_empty_state(hkai__i18n()->get("empty.create_project"), false);
        }
        
        $content = [];

        // 逐專案輸出列表項目（單項渲染共用 render_project_item——清單與單項同一渲染源）
        foreach ($projects as $project_item) {
            $content[] = $this->render_project_item($project_item, $selected_project_slug);
        }

        return implode("\r\n", $content);
    }
}
