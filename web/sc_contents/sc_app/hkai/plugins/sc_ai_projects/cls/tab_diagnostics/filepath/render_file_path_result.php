<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\filepath;

// == sc_ai_projects tab_diagnostics filepath render_file_path_result：tab_diagnostics 域動作（filepath section；trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 自 render_diagnostics_result 分遷入（左側 tab 化——檔案路徑 section） ==

trait render_file_path_result
{
    public function render_file_path_result($file_path_result)
    {
        // i18n 列標籤（單一來源——cls/i18n/lang/*.json）
        $scanned_label     = hkai__i18n()->get("diagnostics.scanned");
        $warn_label        = hkai__i18n()->get("diagnostics.warn");
        $danger_label      = hkai__i18n()->get("diagnostics.danger");
        $critical_label    = hkai__i18n()->get("diagnostics.critical");
        $elapsed_label     = hkai__i18n()->get("diagnostics.elapsed");
        $unit_files        = hkai__i18n()->get("diagnostics.unit_files");
        $unit_ms           = hkai__i18n()->get("diagnostics.unit_ms");
        $read_failed_label = hkai__i18n()->get("git.read_failed");
        $th_level          = hkai__i18n()->get("diagnostics.th_level");
        $th_path           = hkai__i18n()->get("diagnostics.th_path");
        $th_length         = hkai__i18n()->get("diagnostics.th_length");
        $level_warn        = hkai__i18n()->get("diagnostics.lv_warn");
        $level_danger      = hkai__i18n()->get("diagnostics.lv_danger");
        $level_crit        = hkai__i18n()->get("diagnostics.lv_crit");
        $advice_text       = hkai__i18n()->get("diagnostics.advice");
        $truncated_text    = hkai__i18n()->get("diagnostics.truncated");

        $outputLines   = [];

        // 判定：掃描失敗 → 單列錯誤（重新整理走本 pane 鈕——只重載當前 section）
        if ($file_path_result["ok"] !== true) {
            $outputLines[] = "<div class='diagnostics_summary'>";
                $outputLines[] = "<table class='sc_table'>";
                    $outputLines[] = "<tr class='diagnostics_row'>";
                        $outputLines[] = "<td class='diagnostics_key'>" . $read_failed_label . "</td>";
                        $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($file_path_result["error"], ENT_QUOTES) . "</td>";
                    $outputLines[] = "</tr>";
                $outputLines[] = "</table>";
            $outputLines[] = "</div>";
            return implode("\r\n", $outputLines);
        }

        $outputLines[] = $this->get_diagnostics_headline($file_path_result);

        $outputLines[] = "<div class='diagnostics_summary'>";
            $outputLines[] = "<table class='sc_table'>";
                $outputLines[] = "<tr class='diagnostics_row'>";
                    $outputLines[] = "<td class='diagnostics_key'>" . $scanned_label . "</td>";
                    $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($file_path_result["scanned_count"] . " " . $unit_files, ENT_QUOTES) . "</td>";
                $outputLines[] = "</tr>";
                $outputLines[] = "<tr class='diagnostics_row'>";
                    $outputLines[] = "<td class='diagnostics_key'>" . $warn_label . "</td>";
                    $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($file_path_result["warning_count"], ENT_QUOTES) . "</td>";
                $outputLines[] = "</tr>";
                $outputLines[] = "<tr class='diagnostics_row'>";
                    $outputLines[] = "<td class='diagnostics_key'>" . $danger_label . "</td>";
                    $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($file_path_result["danger_count"], ENT_QUOTES) . "</td>";
                $outputLines[] = "</tr>";
                $outputLines[] = "<tr class='diagnostics_row'>";
                    $outputLines[] = "<td class='diagnostics_key'>" . $critical_label . "</td>";
                    $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($file_path_result["critical_count"], ENT_QUOTES) . "</td>";
                $outputLines[] = "</tr>";
                $outputLines[] = "<tr class='diagnostics_row'>";
                    $outputLines[] = "<td class='diagnostics_key'>" . $elapsed_label . "</td>";
                    $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($file_path_result["elapsed_ms"] . " " . $unit_ms, ENT_QUOTES) . "</td>";
                $outputLines[] = "</tr>";
            $outputLines[] = "</table>";
        $outputLines[] = "</div>";

        $problems = $file_path_result["problems"];

        // 判定：有超長路徑 → 附問題清單表（級別／路徑／長度——長度由長至短）
        if (count($problems) > 0) {
            $outputLines[] = "<div class='diagnostics_summary'>";
                $outputLines[] = "<table class='sc_table'>";
                    $outputLines[] = "<tr class='diagnostics_row'>";
                        $outputLines[] = "<td class='diagnostics_th'>" . $th_level . "</td>";
                        $outputLines[] = "<td class='diagnostics_th'>" . $th_path . "</td>";
                        $outputLines[] = "<td class='diagnostics_th'>" . $th_length . "</td>";
                    $outputLines[] = "</tr>";

                    // 逐條問題檔（後端已排序；顯示上限由 Python 側封頂）
                    foreach ($problems as $problem_item) {
                        $problem_level_text = $level_warn;

                        // 判定：危險（221–250）→ 換級別字眼
                        if ($problem_item["level"] === "danger") {
                            $problem_level_text = $level_danger;
                        }

                        // 判定：極度危險（>250）→ 換級別字眼（覆蓋危險）
                        if ($problem_item["level"] === "critical") {
                            $problem_level_text = $level_crit;
                        }

                        $outputLines[] = "<tr class='diagnostics_row'>";
                            $outputLines[] = "<td class='diagnostics_level " . $problem_item["level"] . "'>" . $problem_level_text . "</td>";
                            $outputLines[] = "<td class='diagnostics_path'>" . sc_html_encode($problem_item["path"], ENT_QUOTES) . "</td>";
                            $outputLines[] = "<td class='diagnostics_length'>" . $problem_item["length"] . "</td>";
                        $outputLines[] = "</tr>";
                    }
                $outputLines[] = "</table>";
            $outputLines[] = "</div>";
            $outputLines[] = "<div class='diagnostics_note'>" . $advice_text . "</div>";

            // 判定：清單被截（超過顯示上限）→ 附截斷註記
            if ($file_path_result["truncated"] === true) {
                $outputLines[] = "<div class='diagnostics_note'>" . $truncated_text . "</div>";
            }
        }

        return implode("\r\n", $outputLines);
    }
}
