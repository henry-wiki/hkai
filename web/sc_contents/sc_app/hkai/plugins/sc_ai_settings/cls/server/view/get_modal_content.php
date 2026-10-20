<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\view;

// == sc_ai_settings server get_modal_content：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat server_Class 分遷入（one file one action） ==
// == 2026-10-05 結構改版：分區（Connection／Status／Action／Live logs）——跟 DARK PRO v3 稿示範；動作鈕＝啟用／重新啟動／停用 ==
// == 2026-10-09 footer 全移除（無儲存／重新整理掣——純狀態／操作頁）；host／port 由啟用／重啟時套用入 runtime ==

trait get_modal_content
{
    // Server 設定 modal 內容（header/footer/body 一個函式從頭建到尾；known_state＝已存的測試狀態——get 不測試；server_status＝實測狀態）
    public function get_modal_content($setting_data = [], $known_state = "", $server_status = "stopped")
    {
        $server_host        = $setting_data["host"];
        $server_port        = $setting_data["port"];

        // 狀態文字（兩態——實測存活與否；顏色 class 見 sc_ai_settings.css）
        $server_status_label = hkai__i18n()->get("settings.server.status_stopped");

        // 判定：已啟用 → Running
        if ($server_status === "running") {
            $server_status_label = hkai__i18n()->get("settings.server.status_running");
        }

        // 唯讀屬性（實測已啟用——host／port 運行中不可改）
        $server_readonly_attr = "";

        // 判定：實測已啟用 → 加上唯讀屬性
        if ($server_status === "running") {
            $server_readonly_attr = " readonly";
        }

        // 狀態 icon（依已知狀態——未測試＝空）
        $icon_state_class = "";
        $icon_glyph_class = "fa-solid";

        // 判定：已知 pass → 綠勾
        if ($known_state === "pass") {
            $icon_state_class = "modal-form__icon--pass";
            $icon_glyph_class = "fa-solid fa-circle-check";
        }
        // 判定：已知 warning → 琥珀警告
        else if ($known_state === "warning") {
            $icon_state_class = "modal-form__icon--warning";
            $icon_glyph_class = "fa-solid fa-triangle-exclamation";
        }
        // 判定：已知 error → 紅叉
        else if ($known_state === "error") {
            $icon_state_class = "modal-form__icon--error";
            $icon_glyph_class = "fa-solid fa-circle-xmark";
        }

        // 日誌（Live logs——初始全量：tail 最後 500 行＋offset 增量標記；最新喺頂）
        $server_log_read       = hkai__python_bridge()->read_server_log_after_offset(0, 500);
        $server_log_lines_html = $this->render_server_log_lines($server_log_read["lines"]);
        $server_log_html       = "<div class='settings_server_log' data-slot='server_log' data-log_offset='" . $server_log_read["offset"] . "'>" . $server_log_lines_html . "</div>";

        // === header ===
        $modal_header = [];
        $modal_header[] = "<div class='modal__title'>" . hkai__i18n()->get("settings.server.title") . "</div>";
        $modal_header[] = "<div class='modal__subtitle'>" . hkai__i18n()->get("settings.server.subtitle") . "</div>";

        // === body ===
        $modal_body = [];
        $modal_body[] = "<div class='modal__content-main modal-form'>";

            // === 分區：Connection（連線位址） ===
            $modal_body[] = "<div class='settings_section'>";
                $modal_body[] = "<div class='settings_section_title'>" . hkai__i18n()->get("settings.server.section_connection") . "</div>";

                $modal_body[] = "<div class='modal-form__row'>";
                    $modal_body[] = "<div class='modal-form__icon " . $icon_state_class . "'>";
                        $modal_body[] = "<i class='" . $icon_glyph_class . "'></i>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__info'>";
                        $modal_body[] = "<div class='modal-form__label'>" . hkai__i18n()->get("settings.server.label_host_port") . "</div>";
                        $modal_body[] = "<div class='modal-form__desc'>" . hkai__i18n()->get("settings.server.desc_host_port") . "</div>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__content'>";
                        $modal_body[] = "<div class='modal-form__input_pair'>";

                        $server_host_encoded = sc_html_encode($server_host);
                        $input_attr = [];
                        $input_attr[] = "type='text'";
                        $input_attr[] = "class='modal__input'";
                        $input_attr[] = "data-name='settings[server][host]'";
                        $input_attr[] = "value='{$server_host_encoded}'";

                        $input_attr = implode(" ", $input_attr) . $server_readonly_attr;
                        $modal_body[] = "<input {$input_attr}/>";

                        $server_port_encoded = sc_html_encode($server_port);
                        $input_attr = [];
                        $input_attr[] = "type='text'";
                        $input_attr[] = "class='modal__input modal-form__port'";
                        $input_attr[] = "data-name='settings[server][port]'";
                        $input_attr[] = "value='{$server_port_encoded}'";
                        $input_attr[] = "maxlength='5'";

                        $input_attr = implode(" ", $input_attr) . $server_readonly_attr;
                        $modal_body[] = "<input {$input_attr}/>";

                        $modal_body[] = "</div>";
                        $modal_body[] = "<button type='button' class='button modal-form__port_assign' data-request_btn='auto_assign_port'>" . hkai__i18n()->get("settings.server.btn_auto_assign_port") . "</button>";

                    $modal_body[] = "</div>";
                $modal_body[] = "</div>";
            $modal_body[] = "</div>";

            // === 分區：Status（運行狀態） ===
            $modal_body[] = "<div class='settings_section'>";
                $modal_body[] = "<div class='settings_section_title'>" . hkai__i18n()->get("settings.server.section_status") . "</div>";

                $modal_body[] = "<div class='modal-form__row'>";
                    $modal_body[] = "<div class='modal-form__icon'>";
                        $modal_body[] = "<i class='fa-solid fa-bolt'></i>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__info'>";
                        $modal_body[] = "<div class='modal-form__label'>" . hkai__i18n()->get("settings.server.section_status") . "</div>";
                        $modal_body[] = "<div class='modal-form__desc'>" . hkai__i18n()->get("settings.server.desc_status") . "</div>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__content'>";
                        $modal_body[] = "<span class='settings_server_status settings_server_status--" . $server_status . "'>" . $server_status_label . "</span>";
                    $modal_body[] = "</div>";
                $modal_body[] = "</div>";
            $modal_body[] = "</div>";

            // === 分區：Action（啟停控制） ===
            $modal_body[] = "<div class='settings_section'>";
                $modal_body[] = "<div class='settings_section_title'>" . hkai__i18n()->get("settings.server.section_action") . "</div>";

                $modal_body[] = "<div class='modal-form__row'>";
                    $modal_body[] = "<div class='modal-form__icon'>";
                        $modal_body[] = "<i class='fa-solid fa-play'></i>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__info'>";
                        $modal_body[] = "<div class='modal-form__label'>" . hkai__i18n()->get("settings.server.section_action") . "</div>";
                        $modal_body[] = "<div class='modal-form__desc'>" . hkai__i18n()->get("settings.server.desc_action") . "</div>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='modal-form__content modal-form__content_double'>";

                        // 判定：運行中 → 重新啟動＋停用；未運行 → 只出啟用（三功能按狀態出——唔同時出）
                        if ($server_status === "running") {
                            $modal_body[] = "<button type='button' class='button' data-request_btn='restart_server'>" . hkai__i18n()->get("settings.server.btn_restart") . "</button>";
                            $modal_body[] = "<button type='button' class='button button--danger' data-request_btn='stop_server'>" . hkai__i18n()->get("settings.server.btn_stop") . "</button>";
                        } else {
                            $modal_body[] = "<button type='button' class='button button--primary' data-request_btn='start_server'>" . hkai__i18n()->get("settings.server.btn_start") . "</button>";
                        }
                    $modal_body[] = "</div>";
                $modal_body[] = "</div>";
            $modal_body[] = "</div>";

            // === 分區：Live logs（flex 柱——最新喺頂；觀察器＋1s 增量輪詢） ===
            $modal_body[] = "<div class='settings_section'>";
                $modal_body[] = "<div class='settings_section_title'>" . hkai__i18n()->get("settings.server.section_live_logs") . "</div>";
                $modal_body[] = $server_log_html;
            $modal_body[] = "</div>";

        $modal_body[] = "</div>";

        $modal = [
            "header" => implode("\r\n", $modal_header),
            "body"   => implode("\r\n", $modal_body),
        ];

        return $modal;
    }
}
