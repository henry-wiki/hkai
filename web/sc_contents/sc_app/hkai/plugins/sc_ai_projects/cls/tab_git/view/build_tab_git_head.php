<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git build_tab_git_head：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait build_tab_git_head
{
    public function build_tab_git_head($is_edit)
    {
        $modal_title = hkai__i18n()->get("git.modal_new");

        // 編輯模式 → 標題改 i18n「編輯專案」
        if ($is_edit === true) {
            $modal_title = hkai__i18n()->get("git.modal_edit");
        }

        return $modal_title;
    }
}
