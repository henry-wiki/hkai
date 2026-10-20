<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project project_item_fetch：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-29 單項專案內容端點（前端觀察器鏈——換 children 用） ==
// == 2026-10-07 卡片身份段：入表前先解純 slug（歸檔項帶 archive@ 前綴——對照 chat_item_fetch）；轉向交前端（點擊行卡 href——endpoint 唔再造 link） ==

trait project_item_fetch
{

    // POST 取單一專案項 HTML（取資料端點——一律 200，成敗由內容 ok 決定）
    public function project_item_fetch()
    {
        $input_data = sc_request()->json();

        $project_slug          = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $selected_project_slug = isset($input_data["selected_project_slug"]) ? $input_data["selected_project_slug"] : "";

        // project_slug 缺 → 內容回報失敗（HTTP 仍 200）
        if ($project_slug === "") {
            return sc_response()->json(["ok" => false, "error" => "project_slug is required"]);
        }

        // 查表用純 slug（卡片身份＝段形——歸檔項會帶 archive@ 前綴；同一解析規則）
        $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

        // 判定：專案要真係存在（slug 查得到先算）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"]);
        }

        // 轉義一次過（整個列交 sc_html_encode——同清單渲染一致）
        $project_item = sc_html_encode($project_row);

        // active 比對用純值（語境形 archive@<slug> → 剝語境——URL 原始值交入、查表／比對用純 slug；同一解析規則）
        $selected_project_slug = sc_ai_projects()->project()->get_bare_project_slug($selected_project_slug);

        // active 判定用「目前選取」——project_slug 只係目標（傳 project_slug ≠ 佢係 selected_project_slug）
        $project_item_html = sc_ai_projects()->project()->render_project_item($project_item, $selected_project_slug);

        $output = [];
        $output["ok"]   = true;
        $output["html"] = ["project_item" => $project_item_html];

        // 選取專案嘅路徑（topnav 路徑列用——切換即更新；前端 .text() 注入）
        $output["project_path"] = (string)$project_row["project_path"];

        return sc_response()->json($output);
    }

}
