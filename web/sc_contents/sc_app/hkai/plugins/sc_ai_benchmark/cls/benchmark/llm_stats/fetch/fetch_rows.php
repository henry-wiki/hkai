<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\llm_stats\fetch;

// == sc_ai_benchmark benchmark/llm_stats fetch fetch_rows：域動作（trait——掛入 bench_source_llm_stats_Class；檔＝動作） ==
// == 2026-10-07 自 benchmark/aa 複製後改為 LLM Stats 抓取（官方 API cursor 分頁抓全；Bearer 由規格帶入——__init__.php main 嘅 api_key） ==
// == 2026-10-07 多 entry（7 粒——全 index 形）：models 底（main 共用底規格）＋TrueSkill index（/leaderboard/indexes/all——逐 entry 按 score_field 取該 category）各抓一次；entry 由 index 條目砌 rows（join base release_date）＋排序＋補 rank ==
// == 2026-10-07 Way 1（index 源）：models 形（/stats/v1/models）＋index 形（/leaderboard/indexes/all——TrueSkill 綜合分）各抓一次；index entry 由官方 index 條目砌 rows（join base 嘅 release_date） ==

trait fetch_rows
{
    // 抓取主流程：models 底抓取（規格＝main 共用底；base rows 供 release_date join）＋index 形有先抓一次（TrueSkill）→ 逐 navigation item 砌 rows——回傳 ["ok"=>true, "entry_results"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_rows()
    {
        // 最終規格列（逐項 array_replace_recursive(main, item) 合成）
        $navigation_items = $this->get_bench_source_navigation_items();

        // index 抓取規格（同形 item 嘅抓取規格一致——取任何一項皆等價；後蓋前寫法最簡）
        $index_navigation_item = null;
        // 逐項揀 index 形
        foreach ($navigation_items as $navigation_item) {
            $fetch_shape = $navigation_item["fetch_shape"];
            // 判定：index 形 → 記低（後蓋前）
            if ($fetch_shape === "index") {
                $index_navigation_item = $navigation_item;
            }
        }

        // models 底抓取（規格＝main 共用底；cursor 分頁——base rows 供 release_date join）
        $models_result = $this->fetch_llm_stats_models($this->bench_source_main);
        // 判定：抓取失敗 → 原錯誤直接上報
        if ($models_result["ok"] !== true) {
            return $models_result;
        }
        $base_rows = $models_result["rows"];

        // index 形抓取（有 index entry 先抓——一次回全部 category）
        $index_rows = [];
        // 判定：有 index 形 item → 抓
        if ($index_navigation_item !== null) {
            $index_result = $this->fetch_llm_stats_indexes($index_navigation_item);
            // 判定：抓取失敗 → 原錯誤直接上報
            if ($index_result["ok"] !== true) {
                return $index_result;
            }
            $index_rows = $index_result["rows"];
        }

        // 逐 entry 砌 rows
        $entry_results = [];
        // 逐 navigation item
        foreach ($navigation_items as $navigation_item) {
            $fetch_category = $navigation_item["fetch_category"];
            $fetch_shape    = $navigation_item["fetch_shape"];

            // 判定：index 形 → index 砌法（TrueSkill conservative；join base 嘅 release_date）
            if ($fetch_shape === "index") {
                $entry_rows = $this->build_index_entry_rows($navigation_item, $index_rows, $base_rows);
            }
            else
            {
                // models 形砌法（top_scores 領域分欄切分）
                $entry_rows = $this->build_entry_rows($base_rows, $navigation_item["score_field"]);
            }

            $entry_results[] = [
                "fetch_category"    => $fetch_category,
                "bench_source_file" => $navigation_item["bench_source_file"],
                "fetch_url"         => $navigation_item["fetch_url"],
                "rows"              => $entry_rows,
            ];
        }

        return ["ok" => true, "entry_results" => $entry_results];
    }

    // 官方 models API cursor 分頁抓齊（抓取規格帶入——上限 max_page_count 頁；頁間 sleep）→ base rows（保留 top_scores 全項＋model_id——供 index join）——回傳 ["ok"=>true, "rows"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_llm_stats_models($fetch_spec)
    {
        // 分頁規格（聲明——fetch_paging）
        $fetch_paging   = $fetch_spec["fetch_paging"];
        $length_key     = $fetch_paging["length_key"];
        $page_length    = $fetch_paging["page_length"];
        $cursor_key     = $fetch_paging["cursor_key"];
        $max_page_count = $fetch_paging["max_page_count"];
        $sleep_secs     = $fetch_paging["sleep_secs"];

        // cursor 分頁循環（有 next_cursor 就續抓——直到無）
        $all_models  = [];
        $next_cursor = null;
        $page_number = 0;
        do {
            $page_number++;

            // 本頁請求 URL（cursor 參數——只有續頁先帶）
            $request_url = $fetch_spec["fetch_url"] . "?" . $length_key . "=" . $page_length;
            // 判定：有 cursor → 帶上
            if ($next_cursor !== null) {
                $request_url .= "&" . $cursor_key . "=" . urlencode($next_cursor);
            }

            // 單次請求（傳輸層檢查喺 request_llm_stats_json 內）
            $request_result = $this->request_llm_stats_json($fetch_spec, $request_url);
            // 判定：請求失敗 → 原錯誤上報
            if ($request_result["ok"] !== true) {
                return $request_result;
            }
            $response_body = $request_result["body"];

            // 解析 JSON（官方 models API——頂層 {models:[...], next_cursor, total}）
            $response_data = json_decode($response_body, true);
            // 判定：解析失敗 → 回錯誤
            if (!is_array($response_data)) {
                return ["ok" => false, "error" => "llm_stats json parse failed"];
            }
            // 判定：無 models 鍵 → 回錯誤
            if (!isset($response_data["models"])) {
                return ["ok" => false, "error" => "llm_stats no models key in json"];
            }

            // 合併本頁 models（逐 item 收集）
            foreach ($response_data["models"] as $model_item) {
                $all_models[] = $model_item;
            }

            // next_cursor（缺省或空字串 → null＝無下一頁）
            $next_cursor = isset($response_data["next_cursor"]) ? $response_data["next_cursor"] : null;
            // 判定：空字串 → 當無下一頁
            if ($next_cursor === "") {
                $next_cursor = null;
            }
            // 判定：無下一頁 → 收工
            if ($next_cursor === null) {
                break;
            }
            // 頁數上限防護（防意外死循環）
            if ($page_number >= $max_page_count) {
                break;
            }

            // 頁間休息（避免觸發 rate limit）
            sleep($sleep_secs);
        } while (true);

        // 逐模型組 base row（top_scores 全項保留——逐 entry 再按各自分欄取值；model_id 供 index join）
        $base_rows = [];
        // 逐模型
        foreach ($all_models as $model_item) {
            // 判定：無名鍵 → 跳過
            if (!isset($model_item["name"])) {
                continue;
            }
            // 判定：名為空 → 跳過
            if ($model_item["name"] === "") {
                continue;
            }

            // 從 array 取值先落同名變數
            $model_id = isset($model_item["id"]) ? $model_item["id"] : "";
            $model_name = $model_item["name"];
            $organization = isset($model_item["organization"]) ? $model_item["organization"] : [];
            $organization_name = isset($organization["name"]) ? $organization["name"] : "";
            $license = isset($model_item["license"]) ? $model_item["license"] : [];
            $license_name = isset($license["name"]) ? $license["name"] : "";
            $top_scores = isset($model_item["top_scores"]) ? $model_item["top_scores"] : [];
            $release_date = isset($model_item["release_date"]) ? $model_item["release_date"] : "";

            $base_rows[] = [
                "model_id"          => $model_id,
                "model_name"        => $model_name,
                "organization_name" => $organization_name,
                "license_name"      => $license_name,
                "top_scores"        => $top_scores,
                "release_date"      => $release_date,
            ];
        }

        return ["ok" => true, "rows" => $base_rows];
    }

    // 官方 indexes/all 單次抓取（TrueSkill 綜合分——一隻 response 回全部 category；唔使分頁）→ category 表——回傳 ["ok"=>true, "rows"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_llm_stats_indexes($fetch_spec)
    {
        // 單次請求（URL 照規格——index item 自帶 fetch_url 覆寫 main）
        $request_result = $this->request_llm_stats_json($fetch_spec, $fetch_spec["fetch_url"]);
        // 判定：請求失敗 → 原錯誤上報
        if ($request_result["ok"] !== true) {
            return $request_result;
        }
        $response_body = $request_result["body"];

        // 解析 JSON（官方 indexes API——頂層 {category_id: {…, models:[…]}}）
        $response_data = json_decode($response_body, true);
        // 判定：解析失敗 → 回錯誤
        if (!is_array($response_data)) {
            return ["ok" => false, "error" => "llm_stats indexes json parse failed"];
        }

        // 偵測形狀（至少一個 category 帶 models 鍵——形唔啱唔靜靜寫空檔）
        $detected_category_count = 0;
        // 逐 category 查驗
        foreach ($response_data as $category_data) {
            // 判定：非陣列 → 跳
            if (!is_array($category_data)) {
                continue;
            }
            // 判定：無 models 鍵 → 跳
            if (!isset($category_data["models"])) {
                continue;
            }
            $detected_category_count++;
        }
        // 判定：一個都唔似 → 形唔啱
        if ($detected_category_count === 0) {
            return ["ok" => false, "error" => "llm_stats indexes shape unexpected"];
        }

        return ["ok" => true, "rows" => $response_data];
    }

    // 單次 curl 請求（GET——Bearer 讀規格 api_key＋User-Agent〔WAF 擋冇 UA 嘅請求〕；含傳輸層檢查）——回傳 ["ok"=>true, "body"=>...] 或 ["ok"=>false, "error"=>...]
    private function request_llm_stats_json($fetch_spec, $request_url)
    {
        // API key（規格帶入——__init__.php main 宣告，值讀 .env）
        $api_key = $fetch_spec["api_key"];
        // 判定：key 空 → 回錯誤（唔打 API）
        if ($api_key === "") {
            return ["ok" => false, "error" => "llm_stats api_key is empty"];
        }

        // curl 請求（GET——Bearer 認證＋User-Agent）
        $curl_handle = curl_init($request_url);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, strtoupper($fetch_spec["fetch_method"]));
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        // CA bundle（相對 PHP 執行檔——唔靠 php.ini 寫死路徑）
        $cacert_path = sc_bundled_cacert_path();
        if ($cacert_path !== "") {
            curl_setopt($curl_handle, CURLOPT_CAINFO, $cacert_path);
        }

        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 90);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, "hkai/1.0");
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $api_key,
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

        return ["ok" => true, "body" => $response_body];
    }

    // 由 base rows 依指定分欄砌 entry rows（取值 → 排序 → 補 rank）——回傳 rows 列
    private function build_entry_rows($base_rows, $score_field)
    {
        // 逐模型由 top_scores 切出本 entry 分欄值
        $entry_rows = [];
        // 逐 base row
        foreach ($base_rows as $base_item) {
            $top_scores = $base_item["top_scores"];
            $score = isset($top_scores[$score_field]) ? $top_scores[$score_field] : null;

            $entry_rows[] = [
                "model_name"        => $base_item["model_name"],
                "organization_name" => $base_item["organization_name"],
                "license_name"      => $base_item["license_name"],
                "score"             => $score,
                "release_date"      => $base_item["release_date"],
            ];
        }

        // 排序＋補 rank（共用）
        $ranked_rows = $this->sort_and_rank_rows($entry_rows);

        return $ranked_rows;
    }

    // 由 index rows 依 score_field（category）砌 entry rows（TrueSkill conservative；join base 嘅 release_date）——排序＋補 rank——回傳 rows 列
    private function build_index_entry_rows($navigation_item, $index_rows, $base_rows)
    {
        // index category（本項指定——例 general）
        $score_field = $navigation_item["score_field"];

        // 偵測：該 category 唔存在 → 空 rows（官方榜未有此類）
        $category_data = isset($index_rows[$score_field]) ? $index_rows[$score_field] : null;
        // 判定：唔存在 → 空
        if ($category_data === null) {
            return [];
        }
        // 判定：無 models 鍵 → 空
        if (!isset($category_data["models"])) {
            return [];
        }
        $index_models = $category_data["models"];

        // base rows 建 model_id → release_date 表（index 條目冇 release_date——join 返）
        $release_date_by_model_id = [];
        // 逐 base row 入表
        foreach ($base_rows as $base_item) {
            $release_date_by_model_id[$base_item["model_id"]] = $base_item["release_date"];
        }

        // 逐 index 條目砌 row（score＝TrueSkill conservative）
        $entry_rows = [];
        // 逐 index model
        foreach ($index_models as $index_model_item) {
            $model_id = $index_model_item["model_id"];
            $score    = $index_model_item["conservative"];

            // 判定：base 有該 model → join release_date；冇 → 空
            $release_date = isset($release_date_by_model_id[$model_id]) ? $release_date_by_model_id[$model_id] : "";

            $entry_rows[] = [
                "model_name"        => $index_model_item["model_name"],
                "organization_name" => $index_model_item["organization_name"],
                "license_name"      => "",
                "score"             => $score,
                "release_date"      => $release_date,
            ];
        }

        // 排序＋補 rank（共用）
        $ranked_rows = $this->sort_and_rank_rows($entry_rows);

        return $ranked_rows;
    }

    // 依分欄排序（高在前；缺排尾）＋補 rank（1 起）——回傳排好序嘅 rows 列（兩形 build 共用）
    private function sort_and_rank_rows($entry_rows)
    {
        // 依分欄排序（高在前；缺 → 排最後）
        usort($entry_rows, function ($row_a, $row_b) {
            $score_a = isset($row_a["score"]) ? $row_a["score"] : null;
            $score_b = isset($row_b["score"]) ? $row_b["score"] : null;
            // 判定：A 缺分 → B 唔缺就 B 前、兩邊都缺就平手
            if ($score_a === null) {
                // 判定：B 唔缺 → A 排後
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
