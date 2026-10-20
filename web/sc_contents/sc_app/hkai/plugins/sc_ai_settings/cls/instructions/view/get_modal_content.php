<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\view;

// == sc_ai_settings instructions get_modal_content：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait get_modal_content
{
    // Instructions 頁內容（header／footer／body 一個函式從頭建到尾；instructions_name＝item key、file_content＝storage 工作檔現值）
    public function get_modal_content($instructions_name, $file_content)
    {
        // 顯示名（nav 同名——system_prompt → System Prompt）
        $instructions_display_names = [];
        $instructions_display_names["system_prompt"] = "System Prompt";
        $instructions_display_names["system_env"] = "Environment";

        $instructions_display_name = $instructions_display_names[$instructions_name];

        // === header ===
        $modal_header = [];
        $modal_header[] = "<div class='modal__title'>" . $instructions_display_name . "</div>";

        // 判定：Environment 頁（自動偵測——唯讀）→ 副題講明唯讀同更新方式
        if ($instructions_name === "system_env") {
            $modal_header[] = "<div class='modal__subtitle'>" . hkai__i18n()->get("settings.instructions.subtitle_env_readonly") . "</div>";
        } else {
            $modal_header[] = "<div class='modal__subtitle'>" . hkai__i18n()->get("settings.instructions.subtitle_prompt") . "</div>";
        }

        // === footer ===
        $modal_footer = [];
        $modal_footer[] = "<div class='modal__content-footer'>";

        // 判定：Environment 頁 → Cancel＋Update（唯讀——無 Save／Restore）
        if ($instructions_name === "system_env") {
            $modal_footer[] = "<button type='button' class='button button--secondary' data-btn='close_modal'>" . hkai__i18n()->get("common.cancel") . "</button>";
            $modal_footer[] = "<button type='button' class='button button--primary' data-btn='refresh_system_env'>" . hkai__i18n()->get("common.update") . "</button>";
        } else {
            $modal_footer[] = "<button type='button' class='button button--secondary' data-btn='close_modal'>" . hkai__i18n()->get("common.cancel") . "</button>";
            $modal_footer[] = "<button type='button' class='button button--secondary' data-btn='restore_instructions'>" . hkai__i18n()->get("settings.instructions.btn_restore") . "</button>";
            $modal_footer[] = "<button type='button' class='button button--primary' data-btn='save_instructions'>" . hkai__i18n()->get("common.save") . "</button>";
        }

        $modal_footer[] = "</div>";

        // === body（右側內容欄整塊＝Vditor 編輯畫面——mount 節點＋初始值節點；其餘元素唔要） ===
        $file_content_encoded = sc_html_encode($file_content);

        $modal_body = [];
        $modal_body[] = "<div class='modal__content-main instructions-editor'>";
            $modal_body[] = "<div class='instructions-editor__mount' data-instructions_name='" . $instructions_name . "'></div>";
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
