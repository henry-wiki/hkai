<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git mask_remote_credentials：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait mask_remote_credentials
{
    private function mask_remote_credentials($remote_url)
    {
        return preg_replace('#//[^/@]*@#', '//', $remote_url);
    }
}
