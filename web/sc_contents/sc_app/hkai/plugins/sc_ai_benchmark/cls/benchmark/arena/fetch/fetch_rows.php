<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\arena\fetch;

// == sc_ai_benchmark benchmark/arena fetch fetch_rows：域動作（trait——掛入 bench_source_arena_Class；檔＝動作） ==
// == 2026-10-07 新（LM Arena leaderboard 分頁抓取——HF datasets-server rows API、免 key、頁間 sleep） ==
// == 2026-10-07 多 entry：逐 navigation item 抓（main＋item 合併成最終規格先 send 請求；entry 名＝fetch_category 值） ==

trait fetch_rows
{
    // 逐 navigation item 分頁抓取 rows（各項先合併成最終規格——規格自身 carry 住 fetch_category／bench_source_file／fetch_url／fetch_method／fetch_query／fetch_paging）——回傳 ["ok"=>true, "entry_results"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_rows()
    {
        // 最終規格列（逐項 array_replace_recursive(main, item) 合成）
        $navigation_items = $this->get_bench_source_navigation_items();

        $entry_results = [];

        // 逐 entry 抓（抓完一個休息——避免觸發 rate limit）
        foreach ($navigation_items as $navigation_item) {
            $entry_fetch_result = $this->fetch_rows_for_entry($navigation_item);

            // 判定：本 entry 抓取失敗 → 原錯誤直接上報
            if ($entry_fetch_result["ok"] !== true) {
                return $entry_fetch_result;
            }

            // 本 entry 結果（entry 名＝fetch_category 值；store 檔＝item 自帶）
            $fetch_category = $navigation_item["fetch_category"];
            $entry_results[] = [
                "fetch_category"    => $fetch_category,
                "bench_source_file" => $navigation_item["bench_source_file"],
                "rows"              => $entry_fetch_result["rows"],
            ];

            // 休息（entry 之間——避免觸發 rate limit）
            sleep($navigation_item["fetch_paging"]["sleep_secs"]);
        }

        return ["ok" => true, "entry_results" => $entry_results];
    }

    // 抓單一 entry 全部 rows（分頁掃——數夠／到尾頁即止；規格＝合併後最終規格）——回傳 ["ok"=>true, "rows"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_rows_for_entry($navigation_item)
    {
        // 分頁規格（最終規格自帶——每頁筆數＋頁間休息＋下載上限）
        $fetch_paging = $navigation_item["fetch_paging"];
        // 下載上限（條——最多收集幾多條）
        $max_rows = $fetch_paging["max_rows"];
        // 掃描頁數（下載上限 ÷ 每頁條數——向上取整）
        $max_scan_pages = ceil($max_rows / $fetch_paging["page_length"]);

        $all_rows = [];

        // 分頁 loop（第 0 頁起逐頁掃——數夠／尾頁即止）
        for ($scan_page_index = 0; $scan_page_index < $max_scan_pages; $scan_page_index++) {
            // 本頁 offset（第 0 頁 = 0——每頁 +page_length）
            $offset = $scan_page_index * $fetch_paging["page_length"];

            $page_result = $this->fetch_rows_page($navigation_item, $offset);

            // 判定：本頁抓取失敗 → 原錯誤直接上報
            if ($page_result["ok"] !== true) {
                return $page_result;
            }

            // 收集本頁 rows（逐項——原樣入）
            foreach ($page_result["page_rows"] as $page_row_item) {
                $all_rows[] = $page_row_item;
            }

            // 裁至下載上限（尾頁可能超額——只留上限內）
            if (count($all_rows) > $max_rows) {
                $all_rows = array_slice($all_rows, 0, $max_rows);
            }

            // 判定：收夠下載上限 → 結束 loop
            if (count($all_rows) >= $max_rows) {
                break;
            }

            // 判定：本頁不足一頁 → 已到尾頁，結束 loop
            if (count($page_result["page_rows"]) < $fetch_paging["page_length"]) {
                break;
            }

            // 頁間休息（避免觸發 rate limit）
            sleep($fetch_paging["sleep_secs"]);
        }

        return ["ok" => true, "rows" => $all_rows];
    }

    // 抓取單一 entry 單頁 rows（curl——最終規格嘅 fetch_url＋fetch_query（加埋分頁））——回傳 ["ok"=>true, "page_rows"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_rows_page($navigation_item, $offset)
    {
        // 分頁規格（最終規格自帶——offset／length 參數名＋每頁筆數）
        $fetch_paging = $navigation_item["fetch_paging"];
        $offset_key = $fetch_paging["offset_key"];
        $length_key = $fetch_paging["length_key"];

        // 組裝 URL（最終規格嘅 fetch_url＋fetch_query（加埋分頁參數）→ query string）
        $request_url = $navigation_item["fetch_url"];
        $fetch_query = $navigation_item["fetch_query"];
        $fetch_query[$offset_key] = $offset;
        $fetch_query[$length_key] = $fetch_paging["page_length"];
        $request_url .= "?" . http_build_query($fetch_query);

        // curl 請求（HTTP 方法照最終規格嘅 fetch_method；RETURNTRANSFER 收 body；連線/整體逾時分開設）
        $curl_handle = curl_init($request_url);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, strtoupper($navigation_item["fetch_method"]));
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        // CA bundle（相對 PHP 執行檔——唔靠 php.ini 寫死路徑）
        $cacert_path = sc_bundled_cacert_path();
        if ($cacert_path !== "") {
            curl_setopt($curl_handle, CURLOPT_CAINFO, $cacert_path);
        }

        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 60);
        $response_body = curl_exec($curl_handle);
        $curl_error    = curl_error($curl_handle);
        $curl_status   = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        curl_close($curl_handle);

        // 判定：回應 false → 傳輸層錯誤
        if ($response_body === false) {
            return ["ok" => false, "error" => "curl response false: " . $curl_error];
        }
        // 判定：curl 錯誤字串非空 → 回錯誤
        if ($curl_error !== "") {
            return ["ok" => false, "error" => "curl error: " . $curl_error];
        }
        // 判定：非 200 → 回錯誤（附 body 頭 300 字）
        if ($curl_status !== 200) {
            return ["ok" => false, "error" => "http " . $curl_status . ": " . substr($response_body, 0, 300)];
        }

        // 解析 JSON（無 rows 鍵或非陣列 → 回錯誤）
        $api_data = json_decode($response_body, true);

        // 判定：無 rows 鍵 → 回錯誤（附現有 keys）
        if (!isset($api_data["rows"])) {
            $keys = is_array($api_data) ? implode(", ", array_keys($api_data)) : "not array";
            return ["ok" => false, "error" => "no rows key, keys: " . $keys];
        }
        // 判定：rows 非陣列 → 回錯誤
        if (!is_array($api_data["rows"])) {
            return ["ok" => false, "error" => "rows is not array"];
        }

        // 解包 rows（每項為 {row_idx, row}——只取 row 內容）
        $page_rows = [];

        // 逐項解包（冇 row 鍵 → 跳過）
        foreach ($api_data["rows"] as $rows_item) {
            if (!isset($rows_item["row"])) {
                continue;
            }
            // 判定：row 非陣列 → 跳過
            if (!is_array($rows_item["row"])) {
                continue;
            }
            $page_rows[] = $rows_item["row"];
        }

        return ["ok" => true, "page_rows" => $page_rows];
    }
}
