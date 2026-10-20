<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_remote_text：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_remote_text
{
    private function get_git_remote_text($git_status)
    {
        $remote_text = "（未設定 origin）";

        // 判定：有 origin → 顯示（遮蔽憑證）
        if ($git_status["remote_origin"] !== "") {
            $remote_text = $this->mask_remote_credentials($git_status["remote_origin"]);
        }

        return $remote_text;
    }
}
