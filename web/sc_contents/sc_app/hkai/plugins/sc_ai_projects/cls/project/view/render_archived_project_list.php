<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project render_archived_project_list：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-10-04 已歸檔專案清單渲染（render_project_list 嘅鏡像——對照 render_archived_chat_list 而寫） ==

trait render_archived_project_list
{
    public function render_archived_project_list($selected_project_slug = null)
    {
        // 資料來源經 repo（同 render_project_list 的 pattern——唔經 main 快取）
        $projects = sc_ai_projects()->project()->get_archived_project_rows();
        $projects = sc_html_encode($projects);

        // 判定：一件歸檔都冇 → 空白清單
        if (count($projects) === 0) {
            return "";
        }

        $content = [];

        // 逐專案輸出列表項目（單項渲染共用 render_project_item——歸檔態卡帶還原掣）
        foreach ($projects as $project_item) {
            $content[] = $this->render_project_item($project_item, $selected_project_slug);
        }

        return implode("\r\n", $content);
    }
}
