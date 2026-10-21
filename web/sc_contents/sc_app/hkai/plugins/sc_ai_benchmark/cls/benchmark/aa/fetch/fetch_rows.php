<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa\fetch;

// == sc_ai_benchmark benchmark/aa fetch fetch_rows：域動作（trait——掛入 bench_source_aa_Class；檔＝動作） ==
// == 2026-10-07 新（Artificial Analysis 抓取——官方 API v2 單次攞晒、無分頁；x-api-key 由規格帶入——__init__.php main 嘅 api_key） ==
// == 2026-10-07 多 entry：官方 API 一次抓（全部 entry 共用 base rows）——逐 navigation item 依各自 score_field（領域分欄）切分＋排序＋補 rank ==

trait fetch_rows
{
    // 官方 API 一次抓（全部 entry 共用 base rows）——逐 navigation item 依 score_field 收 rows——回傳 ["ok"=>true, "entry_results"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_rows()
    {
        // 最終規格列（逐項 array_replace_recursive(main, item) 合成）
        $navigation_items = $this->get_bench_source_navigation_items();

        // 官方 API 一次抓（規格照首項合併結果）
        $first_navigation_item = $navigation_items[0];
        $models_result = $this->fetch_aa_models($first_navigation_item);

        // 判定：抓取失敗 → 原錯誤直接上報
        if ($models_result["ok"] !== true) {
            return $models_result;
        }

        $entry_results = [];

        // 逐 entry 依自身 score_field（領域分欄——例 intelligence／coding／math index）切分＋排序＋補 rank
        foreach ($navigation_items as $navigation_item) {
            $fetch_category = $navigation_item["fetch_category"];
            $score_field = $navigation_item["score_field"];
            $entry_rows = $this->build_entry_rows($models_result["rows"], $score_field);

            $entry_results[] = [
                "fetch_category"    => $fetch_category,
                "bench_source_file" => $navigation_item["bench_source_file"],
                "rows"              => $entry_rows,
            ];
        }

        return ["ok" => true, "entry_results" => $entry_results];
    }

    // 抓 AA 官方模型清單（curl 帶 x-api-key）→ base rows（保留 evaluations 全項——逐 entry 再按分欄切）——回傳 ["ok"=>true, "rows"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_aa_models($navigation_item)
    {
        // API key（規格帶入——__init__.php main 宣告，值讀 .env）
        $api_key = $navigation_item["api_key"];

        // 判定：key 空 → 回錯誤（唔打 API）
        if ($api_key === "") {
            return ["ok" => false, "error" => "aa api_key is empty"];
        }

        // curl 請求（GET——x-api-key 認證）
        $curl_handle = curl_init($navigation_item["fetch_url"]);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, strtoupper($navigation_item["fetch_method"]));
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        // CA bundle（相對 PHP 執行檔——唔靠 php.ini 寫死路徑）
        $cacert_path = sc_bundled_cacert_path();
        if ($cacert_path !== "") {
            curl_setopt($curl_handle, CURLOPT_CAINFO, $cacert_path);
        }

        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 90);
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
            "x-api-key: " . $api_key,
        ]);
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

        // 解析 JSON（官方 API——頂層 {status, prompt_options, data:[...]}）
        $response_data = json_decode($response_body, true);
        // 判定：解析失敗 → 回錯誤
        if (!is_array($response_data)) {
            return ["ok" => false, "error" => "aa json parse failed"];
        }
        // 判定：無 data 鍵 → 回錯誤
        if (!isset($response_data["data"])) {
            return ["ok" => false, "error" => "no data key in json"];
        }

        // 逐模型組 base row（evaluations 全項保留——逐 entry 再按各自分欄取值）
        $base_rows = [];
        // 逐模型
        foreach ($response_data["data"] as $model_item) {
            // 判定：無名鍵 → 跳過
            if (!isset($model_item["name"])) {
                continue;
            }
            // 判定：名為空 → 跳過
            if ($model_item["name"] === "") {
                continue;
            }

            // 從 array 取值先落同名變數
            $model_name = $model_item["name"];
            $model_creator = isset($model_item["model_creator"]) ? $model_item["model_creator"] : [];
            $organization = isset($model_creator["name"]) ? $model_creator["name"] : "";
            $evaluations = isset($model_item["evaluations"]) ? $model_item["evaluations"] : [];
            $pricing = isset($model_item["pricing"]) ? $model_item["pricing"] : [];
            $price_1m_blended = isset($pricing["price_1m_blended_3_to_1"]) ? $pricing["price_1m_blended_3_to_1"] : null;
            $release_date = isset($model_item["release_date"]) ? $model_item["release_date"] : "";

            $base_rows[] = [
                "model_name"       => $model_name,
                "organization"     => $organization,
                "evaluations"      => $evaluations,
                "price_1m_blended" => $price_1m_blended,
                "release_date"     => $release_date,
            ];
        }

        return ["ok" => true, "rows" => $base_rows];
    }

    // 由 base rows 依指定分欄砌 entry rows（取值 → 排序（高在前；缺排尾）→ 補 rank）——回傳 rows 列
    private function build_entry_rows($base_rows, $score_field)
    {
        // 逐模型由 evaluations 切出本 entry 分欄值
        $entry_rows = [];
        // 逐 base row
        foreach ($base_rows as $base_item) {
            $evaluations = $base_item["evaluations"];
            $score = isset($evaluations[$score_field]) ? $evaluations[$score_field] : null;

            $entry_rows[] = [
                "model_name"       => $base_item["model_name"],
                "organization"     => $base_item["organization"],
                "score"            => $score,
                "price_1m_blended" => $base_item["price_1m_blended"],
                "release_date"     => $base_item["release_date"],
            ];
        }

        // 依分欄排序（高在前；缺 → 排最後）
        usort($entry_rows, function ($row_a, $row_b) {
            $score_a = isset($row_a["score"]) ? $row_a["score"] : null;
            $score_b = isset($row_b["score"]) ? $row_b["score"] : null;
            // 判定：兩邊都缺分 → 平手（維持原序）
            if ($score_a === null) {
                // 判定：A 缺 B 唔缺 → A 排後
                if ($score_b !== null) {
                    return 1;
                }
                return 0;
            }
            // 判定：B 缺分 → B 排後
            if ($score_b === null) {
                return -1;
            }
            // 判定：同分 → 平手
            if ($score_a == $score_b) {
                return 0;
            }
            return ($score_a < $score_b) ? 1 : -1;
        });

        // 補 rank（1 起——排序後位置）
        $rank_index = 0;
        $ranked_rows = [];
        // 逐行補
        foreach ($entry_rows as $entry_row_item) {
            $rank_index++;
            $entry_row_item["rank"] = $rank_index;
            $ranked_rows[] = $entry_row_item;
        }

        return $ranked_rows;
    }
}
