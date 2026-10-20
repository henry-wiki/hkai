<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\view;

// == sc_ai_projects tab_environment build_tab_environment_body：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==

trait build_tab_environment_body
{
    public function build_tab_environment_body($project_row)
    {
        // 守衛：新建語境（無專案列）→ 無路徑可查，回提示殼
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return $this->build_environment_notice_pane("請先儲存專案資料，再檢視環境資料。");
        }

        // 準確值：環境檔＝storage/hkai/environment/env.{slug}.md（app storage——唔寫專案根）
        $runtime_configs = hkai__core()->get_runtime_configs();
        $storage_dir     = $runtime_configs["storage_dir"];
        $env_file        = $storage_dir . "/environment/env." . $project_row["project_slug"] . ".md";

        // 檢視純讀——零寫入（生成時機＝建立專案；重新偵測另走「更新」掣）
        $env_content = (string)@file_get_contents($env_file);

        // 判定：偵測／寫入失敗（bridge 未運行等）＋無內容 → 提示（撳「更新」重試）
        if (trim($env_content) === "") {
            return $this->build_environment_notice_pane("環境資料尚未產生（偵測失敗）——請撳「更新」重試。");
        }

        // 轉義一次過（檢視器初始值——交 value 節點帶入）
        $env_content_encoded = sc_html_encode($env_content);

        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='environment'>";
            $outputLines[] = "<div class='pane_body instructions-editor' data-edit_mode='0'>";
                $outputLines[] = "<div class='instructions-editor__mount' data-readonly='1'></div>";
                $outputLines[] = "<div class='instructions-editor__value' hidden>" . $env_content_encoded . "</div>";
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }

    // 環境頁提示殼（新建／未產生——同款結構、pane 名唔同）
    private function build_environment_notice_pane($notice_text)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='environment'>";
            $outputLines[] = "<div class='pane_body'>";
                $outputLines[] = sc_empty_state($notice_text, false);
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
