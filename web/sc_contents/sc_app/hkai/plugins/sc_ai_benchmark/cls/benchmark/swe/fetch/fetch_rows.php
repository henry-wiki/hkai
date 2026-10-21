<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\swe\fetch;

// == sc_ai_benchmark benchmark/swe fetch fetch_rows：域動作（trait——掛入 bench_source_swe_Class；檔＝動作） ==
// == 2026-10-07 新（SWE-bench 抓取——GitHub 官方 repo（swe-bench.github.io）data/leaderboards.json、免 key） ==
// == 2026-10-07 多 entry：官方檔一次抓（全部板共用）——逐 navigation item 切板（entry 名＝fetch_category 值＝板名） ==

trait fetch_rows
{
    // 逐 navigation item 切板（官方檔一次抓——每 entry 一個 store 檔）——回傳 ["ok"=>true, "entry_results"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_rows()
    {
        // 最終規格列（逐項 array_replace_recursive(main, item) 合成）
        $navigation_items = $this->get_bench_source_navigation_items();

        // 官方檔一次抓（全部板共用——規格照首項合併結果）
        $first_navigation_item = $navigation_items[0];
        $boards_result = $this->fetch_boards($first_navigation_item);

        // 判定：官方檔抓取失敗 → 原錯誤直接上報
        if ($boards_result["ok"] !== true) {
            return $boards_result;
        }

        $entry_results = [];

        // 逐 entry 切板（entry 名＝fetch_category 值）
        foreach ($navigation_items as $navigation_item) {
            $entry_fetch_result = $this->extract_board_rows($boards_result["boards"], $navigation_item["fetch_category"]);

            // 判定：本 entry 切板失敗 → 原錯誤直接上報
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
        }

        return ["ok" => true, "entry_results" => $entry_results];
    }

    // 抓官方 leaderboards.json（curl——~4MB，逾時設長）——回傳 ["ok"=>true, "boards"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function fetch_boards($navigation_item)
    {
        // curl 請求（抓 leaderboards.json）
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

        // 解析 JSON（GitHub 官方檔——頂層 {leaderboards: [...]}）
        $leaderboard_data = json_decode($response_body, true);
        // 判定：解析失敗 → 回錯誤
        if (!is_array($leaderboard_data)) {
            return ["ok" => false, "error" => "leaderboards json parse failed"];
        }
        // 判定：無 leaderboards 鍵 → 回錯誤
        if (!isset($leaderboard_data["leaderboards"])) {
            return ["ok" => false, "error" => "no leaderboards key in json"];
        }

        return ["ok" => true, "boards" => $leaderboard_data["leaderboards"]];
    }

    // 由板列切出指定 entry 嘅 rows（板名比對大小寫不拘）＋排序＋補 rank——回傳 ["ok"=>true, "rows"=>[...]] 或 ["ok"=>false, "error"=>...]
    private function extract_board_rows($board_list, $fetch_category)
    {
        // 找目標板（name 與 fetch_category 相同——大小寫不拘）
        $target_board = null;
        // 逐板比對
        foreach ($board_list as $board_item) {
            // 判定：無名鍵 → 跳過
            if (!isset($board_item["name"])) {
                continue;
            }
            // 判定：名對上 → 記住即止
            if (strcasecmp($board_item["name"], $fetch_category) === 0) {
                $target_board = $board_item;
                break;
            }
        }
        // 判定：冇目標板 → 回錯誤
        if ($target_board === null) {
            return ["ok" => false, "error" => $fetch_category . " board not found"];
        }

        // 逐行重建新 row 形（view 用：rank／model_name／organization／resolved／date／cost／instance_cost）
        $entry_rows = [];
        // 逐 result 行
        foreach ($target_board["results"] as $result_item) {
            // 判定：冇名 → 跳過
            if (!isset($result_item["name"]) || $result_item["name"] === "") {
                continue;
            }

            // 從 array 取值先落變數（同名）
            $name      = $result_item["name"];
            $model_org = isset($result_item["model_org"]) ? $result_item["model_org"] : "";
            $resolved  = isset($result_item["resolved"]) ? $result_item["resolved"] : null;
            $date      = isset($result_item["date"]) ? $result_item["date"] : "";
            $cost      = isset($result_item["cost"]) ? $result_item["cost"] : null;
            $instance_cost = isset($result_item["instance_cost"]) ? $result_item["instance_cost"] : null;

            $entry_rows[] = [
                "model_name"    => $name,
                "organization"  => $model_org,
                "resolved"      => $resolved,
                "date"          => $date,
                "cost"          => $cost,
                "instance_cost" => $instance_cost,
            ];
        }

        // 依 resolved 排序（高在前；缺 → 排最後）
        usort($entry_rows, function ($row_a, $row_b) {
            $score_a = isset($row_a["resolved"]) ? $row_a["resolved"] : null;
            $score_b = isset($row_b["resolved"]) ? $row_b["resolved"] : null;
            if ($score_a === null && $score_b === null) {
                return 0;
            }
            if ($score_a === null) {
                return 1;
            }
            if ($score_b === null) {
                return -1;
            }
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

        return ["ok" => true, "rows" => $ranked_rows];
    }
}
