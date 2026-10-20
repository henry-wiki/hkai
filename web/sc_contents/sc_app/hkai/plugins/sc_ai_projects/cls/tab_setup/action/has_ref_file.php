<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup has_ref_file：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait has_ref_file
{
    private function has_ref_file($project_path, $head_ref_path)
    {
        // 展開的 ref 檔（.git/refs/heads/xxx）
        if (is_file($project_path . "/.git/" . $head_ref_path)) {
            return true;
        }

        // 未展開（已 pack）→ 查 packed-refs
        $packed_refs_file = $project_path . "/.git/packed-refs";

        // 無 packed-refs → 確定無提交
        if (!is_file($packed_refs_file)) {
            return false;
        }

        $packed_refs_content = (string)@file_get_contents($packed_refs_file);

        return strpos($packed_refs_content, " " . $head_ref_path . "\n") !== false;
    }
}
