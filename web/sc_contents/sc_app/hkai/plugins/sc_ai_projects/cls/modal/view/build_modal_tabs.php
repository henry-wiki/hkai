<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal\view;

use function sc\app\hkai\plugins\sc_ai_projects\modal\tabs;

// == sc_ai_projects modal build_modal_tabs：modal 域動作（trait——掛入 modal_Class；檔＝動作） ==
// == 2026-09-27 自 modal/view.php 分遷入（one file one action） ==

trait build_modal_tabs
{
    public function build_modal_tabs($is_edit, $active_tab_id, $is_archived)
    {
        // active_tab_id 由呼叫端保證有效（fetch_project_form 已驗——內部值唔防禦）
        
        $outputLines = [];
        
        // 依定義表逐個產出 tab 按鈕（定義表在 main initial data）
        foreach (tabs()->get_modal_tabs() as $tab_item) {
            $tab_id          = $tab_item["tab_id"];
            $label           = $tab_item["label"];
            $is_edit_only    = $tab_item["is_edit_only"];
            
            // 僅編輯模式 tab → 非編輯時跳過
            if ($is_edit_only === true) {
                if ($is_edit !== true) {
                    continue;
                }
            }
            
            // 歸檔可見性（always／normal_only／archive_only）→ 按語境過濾
            $archived_visibility = $tab_item["archived_visibility"];

            // 歸檔語境 → 跳過只喺普通語境可見 tab（歸檔＝只剩 表單／刪除 兩粒）
            if ($is_archived === true) {
                // 該 tab 標記 normal_only → 跳過
                if ($archived_visibility === "normal_only") {
                    continue;
                }
            }

            // 非歸檔語境 → 跳過只喺歸檔語境可見 tab（刪除 tab 唔喺普通專案出現）
            if ($is_archived === false) {
                // 該 tab 標記 archive_only → 跳過
                if ($archived_visibility === "archive_only") {
                    continue;
                }
            }
            
            $tab_class = "modal_tab";
            
            // 與預設 tab 相同 → 加 on class
            if ($tab_id === $active_tab_id) {
                $tab_class .= " on";
            }
            
            $outputLines[] = "<button type='button' class='" . $tab_class . "' data-tab_id='" . $tab_id . "'>" . $label . "</button>";
        }
        
        return implode("", $outputLines);
    }
}
