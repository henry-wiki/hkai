<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\view;

// == sc_ai_projects tab_rule render_rule_content：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==
// == 2026-09-27 自 tab_rule/view.php 分遷入（one file one action） ==

trait render_rule_content
{
    public function render_rule_content($rule_content)
    {
        $trimmed_content = trim($rule_content);

        // 判定：空檔／缺檔 → 共用空狀態區塊
        if ($trimmed_content === "") {
            return sc_empty_state("此專案未有 AGENTS.md。", false);
        }

        // 轉義一次過（原文照出——pre-wrap 保留排版）
        $rule_content_html = sc_html_encode($rule_content);

        $outputLines   = [];
        $outputLines[] = "<div class='rule_content'>" . $rule_content_html . "</div>";

        return implode("\r\n", $outputLines);
    }
}
