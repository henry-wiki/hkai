<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\deepseek\view;

// == sc_ai_providers provider/deepseek view get_model_list_page：view 動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-10-06 自 request/view/build_provider_page_html 分遷入（顯示歸各 provider 自己） ==
// == 細件（tbody／row／format）同檔——太簡單唔分檔 ==

trait get_model_list_page
{
    // 砌 provider 頁 HTML（大 table——provider 資訊列＋欄頭＋全部 model 列；後端砌，前端只 insert）
    public function get_model_list_page($provider_id)
    {
        // model 數據（包內自取——repo 子域；單一讀取——response 唔再帶原數據）
        $models = provider()->repo()->get_models();
        $generated_at = provider()->repo()->get_provider_generated_at();

        $output_lines = [];
        $output_lines[] = "<div class='model_selector_provider_page' data-provider_id='" . $provider_id . "'>";
        $output_lines[] = "<table class='model_selector_table'>";
        $output_lines[] = $this->build_tbody_provider_html($provider_id, $generated_at);
        $output_lines[] = "<tbody data-tbody='model_list' data-provider_id='" . $provider_id . "'>";

        // 無 model（provider 未下載／空）→ 空態提示列
        if (empty($models)) {
            $output_lines[] = "<tr class='model_selector_empty_row'><td colspan='5'>No available models — click [Refresh] to download</td></tr>";
        }
        else
        {
            // 逐 model 砌一列（次序照 provider 檔收集）
            foreach ($models as $model_item) {
                $output_lines[] = $this->build_model_row_html($provider_id, $model_item);
            }
        }

        $output_lines[] = "</tbody>";
        $output_lines[] = "</table>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }

    // 砌 provider 資訊 tbody（搜尋框＋下載時間＋Refresh＋欄頭列）
    private function build_tbody_provider_html($provider_id, $generated_at)
    {
        // 下載時間（缺 → N/A）
        $provider_time_text = $generated_at ? $generated_at : "N/A";

        $output_lines = [];
        $output_lines[] = "<tbody data-tbody='provider' class='thead' data-provider_id='" . $provider_id . "'>";
        $output_lines[] = "<tr class='model_selector_provider_row' data-provider_id='" . $provider_id . "'>";
        $output_lines[] = "<td colspan='5'>";
        $output_lines[] = "<div class='model_selector_provider_head'>";
        $output_lines[] = "<div class='modal__search_wrap'>";
        $output_lines[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
        $output_lines[] = "<input class='modal__search_input model_selector_table_search' type='text' placeholder='search / model filtering'>";
        $output_lines[] = "</div>";
        $output_lines[] = "<span class='model_selector_provider_meta'>";
        $output_lines[] = "<span class='model_selector_provider_time'>" . $provider_time_text . "</span>";
        $output_lines[] = "<button class='model_selector_provider_refresh' title='download model list' data-provider_id='" . $provider_id . "'>Refresh</button>";
        $output_lines[] = "</span>";
        $output_lines[] = "</div>";
        $output_lines[] = "</td>";
        $output_lines[] = "</tr>";
        $output_lines[] = "<tr>";
        $output_lines[] = "<td>Name</td>";
        $output_lines[] = "<td class='model_selector_td_context' style='width:100px;'>Context</td>";
        $output_lines[] = "<td class='model_selector_td_price' style='width:100px;'>In / Out $</td>";
        $output_lines[] = "<td class='model_selector_td_index' style='width:100px;'>Coding</td>";
        $output_lines[] = "<td class='model_selector_td_index' style='width:100px;'>Agentic</td>";
        $output_lines[] = "</tr>";
        $output_lines[] = "</tbody>";

        return implode("\r\n", $output_lines);
    }

    // 砌單一 model 列（name 兩行＋context＋價＋index；effort 資料掛 data attrs——前端 refresh 讀）
    private function build_model_row_html($provider_id, $model_item)
    {
        $model_id = $model_item["model_id"];

        // context 縮寫（128000 → 128k；1000000 → 1M）
        $format_context_length = $this->get_format_context_length($model_item["context_length"]);

        // 價（輸入 / 輸出；兩邊都 0 → Free）
        $pricing_text = $model_item["pricing_input"] . " / " . $model_item["pricing_output"];
        // 判定：輸入價 0
        if ($model_item["pricing_input"] === "0") {
            // 判定：輸出價 0 → Free
            if ($model_item["pricing_output"] === "0") {
                $pricing_text = "Free";
            }
        }

        // 快取命中輸入價（第二行——有效值先顯示；空／0 唔出）
        $cache_read_text = "";
        // 判定：非空、非 0 → 砌「cache x」第二行
        if (!in_array($model_item["pricing_input_cache_read"], ["", "0"])) {
            $cache_read_text = "cache " . $model_item["pricing_input_cache_read"];
        }

        $output_lines = [];
        $output_lines[] = "<tr class='model_selector_model_row' data-provider_id='" . $provider_id . "' data-model_id='" . $model_id . "' data-reasoning_supported_efforts='" . implode(",", $model_item["reasoning_supported_efforts"]) . "' data-reasoning_default_effort='" . $model_item["reasoning_default_effort"] . "'>";
        $output_lines[] = "<td class='model_selector_td_name'>";
        $output_lines[] = "<div class='model_selector_model_name'>" . $model_item["name"] . "</div>";
        $output_lines[] = "<div class='model_selector_model_mid'>" . $model_id . "</div>";
        $output_lines[] = "</td>";
        $output_lines[] = "<td class='model_selector_td_context'>" . $format_context_length . "</td>";
        $output_lines[] = "<td class='model_selector_td_price'>";
        $output_lines[] = "<div class='model_selector_price_main'>" . $pricing_text . "</div>";
        $output_lines[] = "<div class='model_selector_price_cache'>" . $cache_read_text . "</div>";
        $output_lines[] = "</td>";
        $output_lines[] = "<td data-td='coding_index' class='model_selector_td_index'>" . $model_item["coding_index"] . "</td>";
        $output_lines[] = "<td data-td='agentic_index' class='model_selector_td_index'>" . $model_item["agentic_index"] . "</td>";
        $output_lines[] = "</tr>";

        return implode("\r\n", $output_lines);
    }

    // context length 格式化（tokens → 縮寫：128000 → 128k、1000000 → 1M；非數字原樣——同前端 get_format_context_length）
    private function get_format_context_length($context_length)
    {
        // 非數字 → 原樣回傳
        if (!is_numeric($context_length)) {
            return (string)$context_length;
        }

        $numeric_length = (float)$context_length;

        // 未達 1000 → 原樣回傳（無縮寫）
        if ($numeric_length < 1000) {
            return (string)$context_length;
        }

        $divisor = 1000;
        $suffix = "k";
        // 判定：達百萬 → 改用 M 縮寫
        if ($numeric_length >= 1000000) {
            $divisor = 1000000;
            $suffix = "M";
        }

        $display_number = $numeric_length / $divisor;
        // 去小數尾零（128.0 → 128；1.5 → 1.5）
        $trimmed_number = (float)number_format($display_number, 1, ".", "");

        return $trimmed_number . $suffix;
    }
}
