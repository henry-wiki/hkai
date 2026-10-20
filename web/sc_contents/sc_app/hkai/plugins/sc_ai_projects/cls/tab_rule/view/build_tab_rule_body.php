<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\view;

// == sc_ai_projects tab_rule build_tab_rule_body：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==
// == 2026-09-27 自 tab_rule/view.php 分遷入（one file one action） ==

trait build_tab_rule_body
{
    public function build_tab_rule_body($project_row)
    {
        // 守衛：新建語境（無專案列）→ 無路徑可讀，回提示殼（同 setup／git 域模式）
        $is_new_project = empty($project_row);
        if ($is_new_project) {
            return $this->build_notice_pane("請先儲存專案資料，再檢視規則。");
        }

        // 準確值：規則檔＝專案根 AGENTS.md（只收 project_path）
        $project_path = $project_row["project_path"];
        $rule_file    = $project_path . "/AGENTS.md";

        // 讀規則檔（PHP 讀檔——缺檔／讀唔到＝空字串）
        $rule_content = (string)@file_get_contents($rule_file);

        // 轉義一次過（編輯器初始值——交 value 節點帶入）
        $rule_content_encoded = sc_html_encode($rule_content);

        // 開啟版本更新時間（儲存送出時嘅比對基準——缺檔＝空字串）
        $rule_update_time = "";
        if (is_file($rule_file)) {
            $rule_update_time = (string)@filemtime($rule_file);
        }

        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='rule'>";
            $outputLines[] = "<div class='pane_body instructions-editor' data-edit_mode='0' data-rule_update_time='" . sc_html_encode($rule_update_time) . "'>";
                $outputLines[] = "<div class='instructions-editor__toolbar'>";
                    $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='toggle_edit_mode' title='編輯／檢視切換'>";
                        $outputLines[] = "<svg class='instructions-editor__icon_edit' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                            $outputLines[] = "<path d='M12 20h9'/>";
                            $outputLines[] = "<path d='M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'/>";
                        $outputLines[] = "</svg>";
                        $outputLines[] = "<svg class='instructions-editor__icon_view' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                            $outputLines[] = "<path d='M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z'/>";
                            $outputLines[] = "<circle cx='12' cy='12' r='3'/>";
                        $outputLines[] = "</svg>";
                    $outputLines[] = "</button>";
                    $outputLines[] = "<div class='instructions-editor__toolbar-group'>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_heading_1' title='標題第一層'>H1</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_heading_2' title='標題第二層'>H2</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_heading_3' title='標題第三層'>H3</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_ordered_list' title='有序列表（1. 2. 3.）'>1. 2. 3.</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_bullet_list' title='無序列表'>•</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn instructions-editor__toolbar-btn--bold' data-btn='insert_bold' title='粗體'>B</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn instructions-editor__toolbar-btn--italic' data-btn='insert_italic' title='斜體'>I</button>";
                        $outputLines[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_code_block' title='Code block（三連 backtick）'>```</button>";
                    $outputLines[] = "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "<div class='instructions-editor__mount' data-readonly='1'></div>";
                $outputLines[] = "<div class='instructions-editor__value' hidden>" . $rule_content_encoded . "</div>";
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
