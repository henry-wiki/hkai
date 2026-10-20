<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\view;

// == sc_ai_projects tab_environment build_tab_environment_foot：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==

trait build_tab_environment_foot
{
    // 環境頁 footer 右側按鈕（更新＝強制重新偵測＋寫檔——內容重生唔傷資料、免確認；一律常顯）
    public function build_tab_environment_foot()
    {
        $update_wrapper   = [];
        $update_wrapper[] = "<div class='sc_btn_wrapper'>";
        $update_wrapper[] = "<button class='project_modal_btn primary btn_item' data-btn='update_tab_environment'>更新</button>";
        $update_wrapper[] = "</div>";

        return implode("\r\n", $update_wrapper);
    }
}
