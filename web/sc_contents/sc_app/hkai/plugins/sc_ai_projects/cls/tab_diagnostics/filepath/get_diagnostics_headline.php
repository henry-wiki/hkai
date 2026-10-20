<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\filepath;

// == sc_ai_projects tab_diagnostics filepath get_diagnostics_headline：tab_diagnostics 域動作（filepath section；trait——掛入 tab_diagnostics_Class；檔＝動作） ==

trait get_diagnostics_headline
{
    // 頭行（標籤＋狀態——狀態顏色：乾淨／警告／危險／極度危險）
    private function get_diagnostics_headline($diagnostics_result)
    {
        $total_count = $diagnostics_result["warning_count"]
            + $diagnostics_result["danger_count"]
            + $diagnostics_result["critical_count"];

        $state_class = "clean";
        $state_text  = hkai__i18n()->get("diagnostics.no_problem");

        // 判定：有警告（201–220）→ warning
        if ($diagnostics_result["warning_count"] > 0) {
            $state_class = "warning";
            $state_text  = $total_count . " " . hkai__i18n()->get("diagnostics.over_long");
        }

        // 判定：有危險（221–250）→ danger（覆蓋 warning）
        if ($diagnostics_result["danger_count"] > 0) {
            $state_class = "danger";
            $state_text  = $total_count . " " . hkai__i18n()->get("diagnostics.over_long");
        }

        // 判定：有極度危險（>250）→ critical（覆蓋 danger）
        if ($diagnostics_result["critical_count"] > 0) {
            $state_class = "critical";
            $state_text  = $total_count . " " . hkai__i18n()->get("diagnostics.over_long");
        }

        $outputLines   = [];
        $outputLines[] = "<div class='diagnostics_headline " . $state_class . "'>";
            $outputLines[] = "<span class='diagnostics_headline_label'>" . hkai__i18n()->get("diagnostics.headline") . "</span>";
            $outputLines[] = "<span class='diagnostics_headline_state'>" . $state_text . "</span>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
