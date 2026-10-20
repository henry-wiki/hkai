<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\view;

// == sc_ai_settings instructions get_system_prompt：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait get_system_prompt
{
    // System Prompt 頁內容（header／footer／body 一個函式從頭建到尾；file_content＝現值、file_updated_at＝自訂版 mtime（空＝default 版）、is_editable＝外部旗標（config editable——0＝唯讀、1＝可編輯））
    public function get_system_prompt($file_content, $file_updated_at, $is_editable)
    {
        // === header ===
        $modal_header = [];
        $modal_header[] = "<div class='modal__title'>" . hkai__i18n()->get("settings.section.system_prompt") . "</div>";
        $modal_header[] = "<div class='modal__subtitle'>" . hkai__i18n()->get("settings.instructions.subtitle_prompt") . "</div>";

        // === footer（左側＝最後更新時間；編輯掣由 is_editable 決定——唔可編輯＝零編輯掣；無 Cancel——關閉用標題列 ✕） ===
        $modal_footer = [];
        $modal_footer[] = "<div class='modal__content-footer'>";

        $instructions_updated_label = hkai__i18n()->get("settings.instructions.default_version");

        // 判定：有最後更新時間（自訂版）→ 顯示時間
        if ($file_updated_at !== "") {
            $instructions_updated_label = hkai__i18n()->get("settings.instructions.updated") . $file_updated_at;
        }

        $modal_footer[] = "<span class='instructions-editor__updated_at'>" . $instructions_updated_label . "</span>";

        // 判定：可編輯（config editable=1）→ 出 Reset／Save 掣
        if ($is_editable) {
            $modal_footer[] = "<button type='button' class='button button--secondary' data-btn='reset_instructions' data-confirm_msg='" . hkai__i18n()->get("settings.instructions.reset_confirm") . "'>" . hkai__i18n()->get("common.reset") . "</button>";
            $modal_footer[] = "<button type='button' class='button button--primary' data-btn='save_instructions' data-confirm_msg='" . hkai__i18n()->get("settings.instructions.save_confirm") . "'>" . hkai__i18n()->get("common.save") . "</button>";
        }

        $modal_footer[] = "</div>";

        // === body（工具列＋mount 節點＋初始值節點；編輯介面由 is_editable 決定；閱讀起始——撳編輯掣先入編輯） ===
        $file_content_encoded = sc_html_encode($file_content);

        $modal_body = [];
        $modal_body[] = "<div class='modal__content-main instructions-editor' data-edit_mode='0'>";

        // 判定：可編輯（config editable=1）→ 出工具列（編輯切換＋格式掣）
        if ($is_editable) {
            $modal_body[] = "<div class='instructions-editor__toolbar'>";
                $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='toggle_edit_mode' title='" . hkai__i18n()->get("settings.instructions.toolbar_toggle") . "'>";
                    $modal_body[] = "<svg class='instructions-editor__icon_edit' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                        $modal_body[] = "<path d='M12 20h9'/>";
                        $modal_body[] = "<path d='M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'/>";
                    $modal_body[] = "</svg>";
                    $modal_body[] = "<svg class='instructions-editor__icon_view' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                        $modal_body[] = "<path d='M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z'/>";
                        $modal_body[] = "<circle cx='12' cy='12' r='3'/>";
                    $modal_body[] = "</svg>";
                $modal_body[] = "</button>";
                $modal_body[] = "<div class='instructions-editor__toolbar-group'>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_heading_1' title='" . hkai__i18n()->get("settings.instructions.toolbar_heading_1") . "'>H1</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_heading_2' title='" . hkai__i18n()->get("settings.instructions.toolbar_heading_2") . "'>H2</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_heading_3' title='" . hkai__i18n()->get("settings.instructions.toolbar_heading_3") . "'>H3</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_ordered_list' title='" . hkai__i18n()->get("settings.instructions.toolbar_ordered_list") . "'>1. 2. 3.</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_bullet_list' title='" . hkai__i18n()->get("settings.instructions.toolbar_bullet_list") . "'>•</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn instructions-editor__toolbar-btn--bold' data-btn='insert_bold' title='" . hkai__i18n()->get("settings.instructions.toolbar_bold") . "'>B</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn instructions-editor__toolbar-btn--italic' data-btn='insert_italic' title='" . hkai__i18n()->get("settings.instructions.toolbar_italic") . "'>I</button>";
                    $modal_body[] = "<button type='button' class='instructions-editor__toolbar-btn' data-btn='insert_code_block' title='" . hkai__i18n()->get("settings.instructions.toolbar_code_block") . "'>```</button>";
                $modal_body[] = "</div>";
            $modal_body[] = "</div>";
        }

        $modal_body[] = "<div class='instructions-editor__mount' data-instructions_name='system_prompt' data-readonly='1'></div>";
        $modal_body[] = "<div class='instructions-editor__value' hidden>" . $file_content_encoded . "</div>";
        $modal_body[] = "</div>";

        $modal = [
            "header" => implode("\r\n", $modal_header),
            "footer" => implode("\r\n", $modal_footer),
            "body"   => implode("\r\n", $modal_body),
        ];

        return $modal;
    }
}
