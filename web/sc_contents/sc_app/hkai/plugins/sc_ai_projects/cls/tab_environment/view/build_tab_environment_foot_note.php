<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\view;

// == sc_ai_projects tab_environment build_tab_environment_foot_note：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==

trait build_tab_environment_foot_note
{
    // 環境頁 footer 左側註記（上排＝環境檔路徑；下排＝有檔＝最後更新時間、無檔＝未產生提示——文案 i18n：environment.*）
    public function build_tab_environment_foot_note($project_row)
    {
        // 守衛：新建語境（無專案列）→ 無路徑可查，左側淨空
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return "";
        }

        // 準確值：環境檔＝storage/hkai/environment/env.{slug}.md（app storage——唔寫專案根）
        $runtime_configs = hkai__core()->get_runtime_configs();
        $storage_dir     = $runtime_configs["storage_dir"];
        $env_file        = $storage_dir . "/environment/env." . $project_row["project_slug"] . ".md";

        // 顯示路徑（統一 "/"——G:/ 樣）
        $display_env_file = str_replace("\\", "/", $env_file);

        // 判定：環境檔存在 → 顯示最後更新時間
        if (is_file($env_file)) {
            $env_updated_at = date("Y-m-d H:i:s", (int)@filemtime($env_file));
            $foot_note_text = hkai__i18n()->get("environment.updated_label") . $env_updated_at;
        } else {
            // 否則 → 未產生提示字眼（撳「更新」重試）
            $foot_note_text = hkai__i18n()->get("environment.not_generated");
        }

        $outputLines   = [];
        $outputLines[] = "<span class='pane_foot_path'>" . sc_html_encode($display_env_file) . "</span>";
        $outputLines[] = "<span class='pane_foot_note'>" . sc_html_encode($foot_note_text) . "</span>";

        return implode("\r\n", $outputLines);
    }
}
