<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\general;

// == sc_ai_projects tab_diagnostics general render_general_result：tab_diagnostics 域動作（general section；trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（左側 tab 化——General section：系統／專案檢查——check_item 卡） ==

trait render_general_result
{
    // General 檢查項（git_installed／path_exists／has_gitignore——資料來自 run_git_status 掃描）
    public function render_general_result($git_status_result)
    {
        // i18n 字眼（單一來源——cls/i18n/lang/*.json）
        $git_installed_label = hkai__i18n()->get("diagnostics.git_installed");
        $git_installed_desc  = hkai__i18n()->get("diagnostics.git_installed_desc");
        $path_exists_label   = hkai__i18n()->get("diagnostics.path_exists");
        $path_exists_desc    = hkai__i18n()->get("diagnostics.path_exists_desc");
        $has_gitignore_label = hkai__i18n()->get("diagnostics.has_gitignore");
        $has_gitignore_desc  = hkai__i18n()->get("diagnostics.has_gitignore_desc");

        // 檢查項清單（label／desc／passed）
        $check_items = [];
        $check_items[] = [
            "label"  => $git_installed_label,
            "desc"   => $git_installed_desc,
            "passed" => ($git_status_result["git_installed"] === true),
        ];
        $check_items[] = [
            "label"  => $path_exists_label,
            "desc"   => $path_exists_desc,
            "passed" => ($git_status_result["path_exists"] === true),
        ];
        $check_items[] = [
            "label"  => $has_gitignore_label,
            "desc"   => $has_gitignore_desc,
            "passed" => ($git_status_result["has_gitignore"] === true),
        ];

        $outputLines = [];

        // 逐項檢查（pass／fail → 樣式與符號）
        foreach ($check_items as $check_item) {
            $check_item_class = "check_item fail";
            $check_item_mark  = "✕";

            // 判定：通過 → pass 樣式＋✓
            if ($check_item["passed"] === true) {
                $check_item_class = "check_item pass";
                $check_item_mark  = "✓";
            }

            $outputLines[] = "<div class='" . $check_item_class . "'>";
                $outputLines[] = "<div class='mark'>" . $check_item_mark . "</div>";
                $outputLines[] = "<div class='cinfo'>";
                    $outputLines[] = "<div class='cname'>" . $check_item["label"] . "</div>";
                    $outputLines[] = "<div class='cdesc'>" . sc_html_encode($check_item["desc"], ENT_QUOTES) . "</div>";
                $outputLines[] = "</div>";
            $outputLines[] = "</div>";
        }

        return implode("\r\n", $outputLines);
    }
}
