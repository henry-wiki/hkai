<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\render;

// == sc_ai_status_bar render render_project_region：專案欄（trait——掛入 status_bar_Class；檔＝一個 region）
// == 職責：直接組 .sc_statusbar--project（📁 專案名＋歸檔入口＋指示器槽）——唔經 groups／items
// == 上游：sc_ai_projects project/view/render_project_sidebar.php；下游：projects 域（專案名、歸檔語境）

trait render_project_region
{
    // 專案欄 HTML（自給自足：三個元素全部即場計）
    public function render_project_region()
    {
        $project_slug = sc_ai_projects()->project()->get_project_slug();

        // 拆語境（archive@<slug> → 純 slug；同時得歸檔標記）
        $resolved_project_slug = sc_ai_projects()->project()->resolve_project_slug($project_slug);
        $lookup_project_slug   = $resolved_project_slug["project_slug"];

        $project_row = sc_ai_projects()->project()->get_project_row($lookup_project_slug);

        $project_name = "—";

        // 判定：查到專案 → 取名稱
        if ($project_row !== null) {
            $project_name = $project_row["project_name"];
        }

        $archive_url   = "/archive/";
        $archive_title = hkai()->i18n()->get("project.archive");
        $archive_icon  = "fa-solid fa-box-archive";

        // 判定：身處歸檔語境 → 顯示離開歸檔
        if ($resolved_project_slug["archive"] === 1) {
            $archive_url   = "/";
            $archive_title = hkai()->i18n()->get("project.archive_leave");
            $archive_icon  = "fa-solid fa-arrow-left";
        }

        $output_lines   = [];
        $output_lines[] = "<div class='sc_statusbar sc_statusbar--project'>";
        $output_lines[] = "<div class='sc_statusbar__item sc_statusbar__item--project'>📁 <span class='sc-text'>" . $project_name . "</span></div>";
        $output_lines[] = "<a class='sc_statusbar__archive' href='" . $archive_url . "' title='" . $archive_title . "'><i class='" . $archive_icon . "'></i></a>";
        $output_lines[] = "<div class='sc_statusbar__indicators' data-region='project'></div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}
