<?php

namespace sc\app\hkai\plugins\sc_ai_providers\request\view;

// == sc_ai_providers request view build_modal_body_html：request 域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-10-06 自 request/_.bridge.cls.php 分遷入（one file one action） ==

trait build_modal_body_html
{
    // 砌 modal body HTML（左側欄（搜尋框＋provider 清單——按類別分組）＋右內容（單一內容位）——後端砌，前端只 insert）
    private function build_modal_body_html($available_providers)
    {
        // 按類別歸組（聚合器行先、直連在後；組內維持聲明次序）
        $provider_groups               = [];
        $provider_groups["aggregator"] = [];
        $provider_groups["direct"]     = [];

        // 逐個 provider 歸組（類別缺省＝直連）
        foreach ($available_providers as $provider_id => $provider_item) {
            $provider_kind = isset($provider_item["provider_kind"]) ? $provider_item["provider_kind"] : "direct";

            // 判定：類別未知 → 當直連（唔開新組）
            if (!isset($provider_groups[$provider_kind])) {
                $provider_kind = "direct";
            }

            $provider_groups[$provider_kind][$provider_id] = $provider_item;
        }

        $output_lines   = [];
        $output_lines[] = "<div class='modal__navigation'>";
        $output_lines[] = "<div class='modal__search_wrap'>";
        $output_lines[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
        $output_lines[] = "<input id='model_selector_search' class='modal__search_input' type='text' placeholder='" . hkai()->i18n()->get("provider.search_filter") . "'>";
        $output_lines[] = "</div>";
        $output_lines[] = "<div id='side_provider_list' class='modal__navigation-content'>";

            // 逐組出標題＋該組 provider 掣
            foreach ($provider_groups as $group_kind => $group_providers) {
                // 判定：空組 → 跳過（唔出空標題）
                if (count($group_providers) === 0) {
                    continue;
                }

                $group_label    = hkai()->i18n()->get("provider.kind." . $group_kind);
                $output_lines[] = "<div class='side_provider_group_label' data-provider_kind='" . $group_kind . "'>" . $group_label . "</div>";

                // 逐 provider 一粒側欄掣（名由可用 provider 聲明取）
                foreach ($group_providers as $provider_id => $provider_item) {
                    $provider_name  = $provider_item["provider_name"];
                    $output_lines[] = "<div class='side_provider_item'>";
                    $output_lines[] = "<button class='side_provider_tab_button' data-provider_id='" . $provider_id . "'>" . $provider_name . "</button>";
                    $output_lines[] = "</div>";
                }
            }

        $output_lines[] = "</div>";
        $output_lines[] = "</div>";
        $output_lines[] = "<div class='modal__content'>";
        $output_lines[] = "<div id='model_selector_table_wrap' class='model_selector_table_wrap'></div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}
