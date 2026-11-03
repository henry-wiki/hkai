<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project get_project_item_html：單一專案卡 HTML（trait——掛入 project_Class；檔＝動作）
// == 職責：slug → 專案列 → render 卡片 HTML（唔經 HTTP；health_stream 事件直接帶走）
// == 上游：sc_stream state 線（project 變動事件）；下游：get_project_row()、render_project_item()

trait get_project_item_html
{
    // 取單一專案卡 HTML（查唔到專案＝null；$selected_project_slug＝當前頁專案段，用嚟標 active）
    public function get_project_item_html($project_slug, $selected_project_slug)
    {
        // 查表用純 slug（卡片身份＝段形——歸檔項帶 archive@ 前綴）
        $resolved_project_slug = $this->resolve_project_slug($project_slug);
        $lookup_project_slug   = $resolved_project_slug["project_slug"];

        $project_row = $this->get_project_row($lookup_project_slug);

        // 判定：專案唔存在 → 無 HTML
        if ($project_row === null) {
            return null;
        }

        // 轉義一次過（整個列交 sc_html_encode——同清單渲染一致）
        $project_item = sc_html_encode($project_row);

        // active 比對用純值（語境形 archive@<slug> → 剝語境）
        $resolved_selected_project_slug = $this->resolve_project_slug($selected_project_slug);
        $lookup_selected_project_slug   = $resolved_selected_project_slug["project_slug"];

        return $this->render_project_item($project_item, $lookup_selected_project_slug);
    }
}
