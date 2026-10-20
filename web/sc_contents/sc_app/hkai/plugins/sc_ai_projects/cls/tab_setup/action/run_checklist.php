<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup run_checklist：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait run_checklist
{
    public function run_checklist($project_path, $description)
    {
        $checklist_definition = require __DIR__ . "/../checklist.php";

        $checklist_items = [];
        $all_passed      = true;

        // 逐項執行檢查（依定義順序輸出——前端照序顯示）
        foreach ($checklist_definition as $check_key => $check_item) {
            $check_method = $check_item["check"];

            $passed = $this->{$check_method}($project_path, $description);

            $checklist_items[] = [
                "key"          => $check_key,
                "label"        => $check_item["label"],
                "group"        => $check_item["group"],
                "scope"        => $check_item["scope"],
                "hint"         => $check_item["hint"],
                "passed"       => $passed,
            ];

            // 任一項未通過 → 整體未通過
            if ($passed === false) {
                $all_passed = false;
            }
        }

        return ["all_passed" => $all_passed, "items" => $checklist_items];
    }
}
