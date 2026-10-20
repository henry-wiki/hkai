<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\view;

// == sc_ai_projects tab_rule build_tab_rule_foot：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==
// == 2026-09-27 自 tab_rule/view.php 分遷入（one file one action） ==

trait build_tab_rule_foot
{
    // 規則頁 footer 右側按鈕（重設＋儲存——重設＝再三確認後清除專案根 AGENTS.md（後端先備份）；儲存＝規則現值寫入——一律常顯）
    public function build_tab_rule_foot()
    {
        $button_lines = [];

        // 重設鈕（data-btn=reset_tab_rule → 交 tab_rule binder；兩道確認文案＝data-confirm_msg／data-confirm_msg_second）
        $reset_confirm_msg        = hkai__i18n()->get("rule.reset_confirm");
        $reset_confirm_msg_second = hkai__i18n()->get("rule.reset_confirm_again");

        $reset_wrapper   = [];
        $reset_wrapper[] = "<div class='sc_btn_wrapper'>";
        $reset_wrapper[] = "<button class='project_modal_btn btn_item' data-btn='reset_tab_rule' data-confirm_msg='" . sc_html_encode($reset_confirm_msg) . "' data-confirm_msg_second='" . sc_html_encode($reset_confirm_msg_second) . "'>重設</button>";
        $reset_wrapper[] = "</div>";
        $button_lines[]  = implode("\r\n", $reset_wrapper);

        // 儲存鈕（data-btn=save_tab_rule → 交 tab_rule binder 送出；data-confirm_msg＝撳掣先出確認框；無取消鈕——關閉交基座 ✕）
        $save_confirm_msg = hkai__i18n()->get("rule.save_confirm");

        $save_wrapper   = [];
        $save_wrapper[] = "<div class='sc_btn_wrapper'>";
        $save_wrapper[] = "<button class='project_modal_btn primary btn_item' data-btn='save_tab_rule' data-confirm_msg='" . sc_html_encode($save_confirm_msg) . "'>儲存</button>";
        $save_wrapper[] = "</div>";
        $button_lines[] = implode("\r\n", $save_wrapper);

        return implode("\r\n", $button_lines);
    }
}
