<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\git;

// == sc_ai_projects tab_diagnostics git render_git_config_result：tab_diagnostics 域動作（git section；trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（左側 tab 化——Git section：設定一覽攤開；未初始化＝檢查項＋右側 git init 掣） ==

trait render_git_config_result
{
    public function render_git_config_result($git_config_result)
    {
        // i18n 字眼（單一來源——cls/i18n/lang/*.json）
        $read_failed_label     = hkai__i18n()->get("git.read_failed");
        $not_initialized_label = hkai__i18n()->get("git.row_not_initialized");
        $status_label          = hkai__i18n()->get("git.row_status");
        $th_key_label          = hkai__i18n()->get("diagnostics.cfg_key");
        $th_value_label        = hkai__i18n()->get("diagnostics.cfg_value");

        $outputLines = [];

        // 判定：讀取失敗 → 單列錯誤（重新整理走本 pane 鈕——只重載當前 section）
        if ($git_config_result["ok"] !== true) {
            $outputLines[] = "<div class='diagnostics_summary'>";
                $outputLines[] = "<table class='sc_table'>";
                    $outputLines[] = "<tr class='diagnostics_row'>";
                        $outputLines[] = "<td class='diagnostics_key'>" . $read_failed_label . "</td>";
                        $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($git_config_result["error"], ENT_QUOTES) . "</td>";
                    $outputLines[] = "</tr>";
                $outputLines[] = "</table>";
            $outputLines[] = "</div>";
            return implode("\r\n", $outputLines);
        }

        // 判定：非 git 工作樹 → 未初始化（右側附 git init 掣——動作接現成 [data-btn='git_init']；形同 tab_git render_git_check_item）
        if ($git_config_result["is_repo"] !== true) {
            $outputLines[] = "<div class='check_item fail'>";
                $outputLines[] = "<div class='mark'>✕</div>";
                $outputLines[] = "<div class='cinfo'>";
                    $outputLines[] = "<div class='cname'>" . $status_label . "</div>";
                    $outputLines[] = "<div class='cdesc'>" . sc_html_encode($not_initialized_label, ENT_QUOTES) . "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "<button type='button' class='act' data-btn='git_init'>git init</button>";
            $outputLines[] = "</div>";
            return implode("\r\n", $outputLines);
        }

        $configs = $git_config_result["configs"];

        // 重點設定檢查卡（放表格上面——用戶指定「重要嘅先放上面」；資料＝同一份 config 清單）
        $outputLines[] = $this->render_git_key_configs($git_config_result["configs"]);

        $outputLines[] = "<div class='diagnostics_summary'>";
            $outputLines[] = "<table class='sc_table'>";
                $outputLines[] = "<tr class='diagnostics_row'>";
                    $outputLines[] = "<td class='diagnostics_th'>" . $th_key_label . "</td>";
                    $outputLines[] = "<td class='diagnostics_th'>" . $th_value_label . "</td>";
                $outputLines[] = "</tr>";

                // 逐項設定（key／value——全部攤開）
                foreach ($configs as $config_item) {
                    $outputLines[] = "<tr class='diagnostics_row'>";
                        $outputLines[] = "<td class='diagnostics_th'>" . sc_html_encode($config_item["key"], ENT_QUOTES) . "</td>";
                        $outputLines[] = "<td class='diagnostics_val'>" . sc_html_encode($config_item["value"], ENT_QUOTES) . "</td>";
                    $outputLines[] = "</tr>";
                }
            $outputLines[] = "</table>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
