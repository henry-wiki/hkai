<?php

namespace sc\app\hkai\plugins\sc_ai_settings\general\view;

// == sc_ai_settings general handle_fetch_general：general 域動作（trait——掛入 general_Class；檔＝動作） ==
// == 職責：General 分頁（後端渲染；回 {ok, html:{header, body, footer}}——未開放行右欄暫示 common.coming_soon） ==

trait handle_fetch_general
{
    // POST fetch_general handler（General 分頁——右欄內容；左選單由 settings 體系維持）
    public function handle_fetch_general()
    {
        // === header ===
        $modal_header   = [];
        $modal_header[] = "<div class='modal__title'>General</div>";

        // General 偏好清單（聲明式一檔表——加項／改選項＝只改呢份表；讀寫＝sc_config/hkai/settings.json——讀 read_effective_settings／寫 settings_save_general）
        // 有 options＝已開放（出下拉）；淨 label＝未開放（右欄暫示「努力製作中」）
        // 次序：語言／外觀（介面語言、主題）

        // 設定（sc_config/hkai/settings.json——本頁＝general 組；下拉顯示存檔現值）
        $effective_settings = hkai__core()->read_effective_settings();
        $general_settings   = $effective_settings["general"] ?? [];
        $language           = $general_settings["language"] ?? "zh-HK";

        $general_rows = [];
        $general_rows[] = [
            "key"      => "language",
            "label"    => hkai__i18n()->get("settings.general.language_label"),
            "options"  => ["zh-HK" => "繁體中文", "en-US" => "English"],
            "selected" => $language,
        ];
        $general_rows[] = [
            "label" => hkai__i18n()->get("settings.general.theme_label"),
        ];

        // === body ===
        $modal_body   = [];
        $modal_body[] = "<div class='modal__content-main modal-form'>";

                // 逐項生成偏好列（照 projects build_form_row 現成 pattern：icon＋info＋content）
                foreach ($general_rows as $general_row_item) {
                    $row_label = $general_row_item["label"];

                    // 有選項＝已開放——行掛 form_row（錯誤槽契約）
                    $row_has_options = isset($general_row_item["options"]);

                    $row_class = "modal-form__row";
                    if ($row_has_options) {
                        $row_class = "modal-form__row form_row";
                    }

                    $modal_body[] = "<div class='" . $row_class . "'>";
                        $modal_body[] = "<div class='modal-form__icon'></div>";
                        $modal_body[] = "<div class='modal-form__info'>";
                            $modal_body[] = "<div class='modal-form__label'>" . $row_label . "</div>";
                        $modal_body[] = "</div>";
                        $modal_body[] = "<div class='modal-form__content'>";

                    // 已開放 → 出下拉（data-field／form_err 契約，照 §18）
                    if ($row_has_options) {
                        $row_key      = $general_row_item["key"];
                        $row_options  = $general_row_item["options"];
                        $row_selected = $general_row_item["selected"];

                        $modal_body[] = "<select class='modal__input' data-field='" . $row_key . "'>";

                        // 逐個選項（selected 先落變數再拼）
                        foreach ($row_options as $option_value => $option_label) {
                            $selected_attr = "";
                            if ($option_value === $row_selected) {
                                $selected_attr = " selected";
                            }

                            $modal_body[] = "<option value='" . $option_value . "'" . $selected_attr . ">" . $option_label . "</option>";
                        }

                        $modal_body[] = "</select>";
                        $modal_body[] = "<div class='form_err'></div>";
                    }
                    // 未開放 → 右欄暫示純文字
                    else {
                        $modal_body[] = hkai__i18n()->get("common.coming_soon");
                    }

                        $modal_body[] = "</div>";
                    $modal_body[] = "</div>";
                }

        $modal_body[] = "</div>";

        // === footer ===（儲存掣——內容底固定列（官樣 modal__content-footer）；sc_btn_wrapper＝載入遮罩定位容器；handler＝handle_settings_save_general）
        $modal_footer   = [];
        $modal_footer[] = "<div class='modal__content-footer'>";
            $modal_footer[] = "<div class='sc_btn_wrapper'>";
                $modal_footer[] = "<button type='button' class='button button--primary' data-btn='general_save'>" . hkai__i18n()->get("common.save") . "</button>";
            $modal_footer[] = "</div>";
        $modal_footer[] = "</div>";

        $html = [
            "header" => implode("\r\n", $modal_header),
            "footer" => implode("\r\n", $modal_footer),
            "body"   => implode("\r\n", $modal_body),
        ];

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
