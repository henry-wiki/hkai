<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\view;

// == sc_ai_projects tab_rule build_tab_rule_foot_note：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==

trait build_tab_rule_foot_note
{
    // 規則頁 footer 左側註記（上排＝規則檔路徑；下排＝有規則檔＝最後更新時間、無檔／空檔＝默認版本提示字眼——文案 i18n：rule.*）
    public function build_tab_rule_foot_note($project_row)
    {
        // 守衛：新建語境（無專案列）→ 無檔可查，左側淨空
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return "";
        }

        // 準確值：規則檔＝專案根 AGENTS.md（只收 project_path）
        $project_path = $project_row["project_path"];
        $rule_file    = $project_path . "/AGENTS.md";

        $rule_content = (string)@file_get_contents($rule_file);

        // 判定：專案已有規則（非空檔）→ 顯示最後更新時間（標籤＝i18n）
        if (trim($rule_content) !== "") {
            $rule_updated_at = date("Y-m-d H:i:s", (int)@filemtime($rule_file));
            $foot_note_text  = hkai__i18n()->get("rule.updated_label") . $rule_updated_at;
        } else {
            // 否則（缺檔／空檔）→ 默認版本提示字眼（i18n——告訴用戶可撳儲存建立）
            $foot_note_text = hkai__i18n()->get("rule.default_note");
        }

        // 顯示路徑（統一 "/"——G:/ 樣）
        $display_rule_file = str_replace("\\", "/", $rule_file);

        $outputLines   = [];
        $outputLines[] = "<span class='pane_foot_path'>" . sc_html_encode($display_rule_file) . "</span>";
        $outputLines[] = "<span class='pane_foot_note'>" . sc_html_encode($foot_note_text) . "</span>";

        return implode("\r\n", $outputLines);
    }
}
