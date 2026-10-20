<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup check_has_commit：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait check_has_commit
{
    public function check_has_commit($project_path, $description)
    {
        $head_file    = $project_path . "/.git/HEAD";

        // 無 HEAD → 尚未初始化
        if (!is_file($head_file)) {
            return false;
        }

        $head_content = trim((string)@file_get_contents($head_file));

        // 指向分支（ref: refs/heads/xxx）→ 查該 ref 是否有值
        if (strpos($head_content, "ref: ") === 0) {
            $head_ref_path = trim(substr($head_content, 5));

            return $this->has_ref_file($project_path, $head_ref_path);
        }

        // detached HEAD（內容直接是 SHA）→ 有提交
        return $head_content !== "";
    }
}
