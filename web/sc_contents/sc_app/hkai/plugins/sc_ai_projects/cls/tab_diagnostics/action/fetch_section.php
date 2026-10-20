<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects tab_diagnostics fetch_section：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（每 tab 獨立——單獨載入一個 section：自己嗰份掃描＋回 section html） ==

trait fetch_section
{
    public function fetch_section()
    {
        $input_data = sc_request()->json();

        // project_slug 前端必帶（缺＝不合法輸入——直接回失敗）
        $project_slug = trim($input_data["project_slug"] ?? "");
        if ($project_slug === "") {
            return sc_response()->json(["ok" => false, "error" => "project_slug is required"], 400);
        }

        // section_id 只認清單內嘅（單一來源——initial data；唔合法＝400）
        $section_id = trim($input_data["section_id"] ?? "");
        $diagnostics_sections = $this->get_initial_data("diagnostics_sections");
        if (!in_array($section_id, array_keys($diagnostics_sections), true)) {
            return sc_response()->json(["ok" => false, "error" => "unknown section_id"], 400);
        }

        $project_row = repo_select()->get_project_row($project_slug);

        // 專案不存在 → 404
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"], 404);
        }

        $section_html = $this->build_diagnostics_section($project_row, $section_id);

        return sc_response()->json(["ok" => true, "section_id" => $section_id, "html" => $section_html]);
    }
}
