<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project project_list_fetch：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_view.php 分遷入（one file one action） ==

trait project_list_fetch
{
    public function project_list_fetch()
    {
        $input_data = sc_request()->json();
        
        // 目前選取的專案 slug（由前端帶入 select_project_slug——空＝未選（home——冇 active））
        $selected_project_slug = trim($input_data["select_project_slug"] ?? "");
        
        // 備註：未指定專案＝冇 active（home——唔自動揀；自動選取（最近活動／首個）待未來需求——get_latest_activity_project／get_default_project 保留唔叫）
        
        // 清單語境旗標（歸檔語境＝archive／archive@<project_slug>——URL 第一段原值由前端帶；旗標由後台出，前端據此上歸檔視覺）
        $is_archive = $this->is_archive_project_key($selected_project_slug);

        // 瀏覽高亮：語境形（archive@<project_slug>）→ 取純 slug 作 active（路徑共用）；純關鍵字（archive——列面）→ 原值對唔中任何 slug＝冇高亮
        if (strpos($selected_project_slug, "@") !== false) {
            $selected_project_slug = $this->get_bare_project_slug($selected_project_slug);
        }

        // 判定：歸檔語境 → 已歸檔清單；普通語境 → 正常清單
        $project_list_html = "";
        if ($is_archive === true) {
            $project_list_html = $this->render_archived_project_list($selected_project_slug);
        } else {
            $project_list_html = $this->render_project_list($selected_project_slug);
        }
        
        // 選取專案嘅路徑（topnav 路徑列用——前端 .text() 注入；未選 → 空）
        $selected_project_row = sc_ai_projects()->project()->get_project_row($selected_project_slug);
        $project_path = "";
        // 判定：專案列存在 → 取路徑
        if ($selected_project_row !== null) {
            $project_path = (string)$selected_project_row["project_path"];
        }
        
        // 輸出（清單 HTML＋選取專案路徑＋歸檔語境旗標；URL 一律唔郁——本動作唔回 link）
        $output = [];
        $output["ok"]   = true;
        $output["html"] = $project_list_html;
        $output["project_path"] = $project_path;
        $output["is_archive"]   = $is_archive;
        
        return sc_response()->json($output);
    }
}
