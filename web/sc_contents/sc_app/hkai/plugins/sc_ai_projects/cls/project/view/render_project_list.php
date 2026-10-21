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

        $content = [];

        // 逐專案輸出列表項目（Home 列用專屬卡——冇編輯掣／冇時間；其餘照常）
        foreach ($projects as $project_item) {
            // 判定：Home 專案列（slug＝home）→ Home 卡
            if (($project_item["project_slug"] ?? "") === "home") {
                $content[] = sc_ai_projects()->project()->render_home_project_item($project_item, $selected_project_slug);
                continue;
            }

            $content[] = sc_ai_projects()->project()->render_project_item($project_item, $selected_project_slug);
        }

        // 判定：完全冇專案列（連 Home 都冇）→ 才顯示空狀態（有 Home 就唔叫用戶建立）
        if (count($projects) < 1) {
            $content[] = sc_empty_state(hkai__i18n()->get("empty.create_project"), false);
        }

        return implode("\r\n", $content);
    }

    // 渲染 Home 清單項（同一渲染源＋Home 模式：href "/"、冇齒輪；空 slug／home 段高亮）
    public function render_home_project_item($project_item, $selected_project_slug)
    {
        $home_selected_slug = $selected_project_slug;

        // 判定：未選任何專案（空／null）→ 當選中 home（高亮）
        if ($home_selected_slug === "" || $home_selected_slug === null) {
            $home_selected_slug = "home";
        }

        return sc_ai_projects()->project()->render_project_item($project_item, $home_selected_slug, true);
    }
}
