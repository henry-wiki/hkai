<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_sync_text：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_sync_text
{
    private function get_git_sync_text($git_status)
    {
        $sync_text = "（未設定 upstream）";

        // 判定：有 upstream → 顯示開叉
        if ($git_status["upstream"] !== "") {
            $sync_text = $git_status["upstream"] . " ↑" . $git_status["ahead"] . " ↓" . $git_status["behind"];
        }

        return $sync_text;
    }
}
