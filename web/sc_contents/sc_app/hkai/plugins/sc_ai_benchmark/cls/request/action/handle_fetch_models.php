<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\action;

// == sc_ai_benchmark request handle_fetch_models：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 撳左欄導航逐個載入——單一 bench_source 指定 entry 嘅 model 列表（bench_source_id＋entry 指定） ==

trait handle_fetch_models
{
    // POST fetch_models：單一 bench_source 指定 entry model 列表（bench_source_id＋entry 指定；bench_source 須可用先有數）
    public function handle_fetch_models()
    {
        $input_data = sc_request()->json();
        $bench_source_id = isset($input_data["bench_source_id"]) ? $input_data["bench_source_id"] : "";
        $entry_name = isset($input_data["entry"]) ? $input_data["entry"] : "";
        
        // 可用 bench_source（聲明——存在＝前台可用）
        $available_bench_source = sc_ai_benchmark()->main()->get_available_bench_source();
        
        // bench_source 唔可用（唔喺聲明）→ 400（前端顯示失敗）
        if (!isset($available_bench_source[$bench_source_id])) {
            return sc_response()->json(["ok" => false, "error" => "unknown bench_source: " . $bench_source_id], 400);
        }
        
        // 該 bench_source 嘅 navigation 列（聲明——entry 名＝fetch_category 值；驗證用）
        $bench_source_item = $available_bench_source[$bench_source_id];
        $bench_source_navigation = $bench_source_item["navigation"];
        $entry_names = [];
        // 逐 navigation item 收集名（fetch_category 值）
        foreach ($bench_source_navigation as $navigation_item) {
            $entry_names[] = $navigation_item["fetch_category"];
        }

        // entry 唔喺聲明 → 400（前端顯示失敗）
        if (!in_array($entry_name, $entry_names)) {
            return sc_response()->json(["ok" => false, "error" => "unknown entry: " . $entry_name], 400);
        }

        // model 列表頁（具名分派——逐源直接跳；F12 單一 target；數據由包內自取）
        if ($bench_source_id === "arena") {
            $model_list_page = sc_ai_benchmark()->instance__arena()->view()->get_model_list_page($bench_source_id, $entry_name);
        }
        // 具名分派（swe——同 arena 形）
        elseif ($bench_source_id === "swe") {
            $model_list_page = sc_ai_benchmark()->instance__swe()->view()->get_model_list_page($bench_source_id, $entry_name);
        }
        // 具名分派（aa——同 swe 形）
        elseif ($bench_source_id === "aa") {
            $model_list_page = sc_ai_benchmark()->instance__aa()->view()->get_model_list_page($bench_source_id, $entry_name);
        }
        // 具名分派（llm_stats——同 aa 形）
        elseif ($bench_source_id === "llm_stats") {
            $model_list_page = sc_ai_benchmark()->instance__llm_stats()->view()->get_model_list_page($bench_source_id, $entry_name);
        } else {
            // 無對應 bench_source（唔係 arena）→ 400（前端顯示失敗）
            return sc_response()->json(["ok" => false, "error" => "unknown bench_source: " . $bench_source_id], 400);
        }
        
        // bench_source 名（標題用——main 段；多 entry 共用）
        $bench_source_main = $bench_source_item["main"];
        $bench_source_name = $bench_source_main["bench_source_name"];
        
        // html：bench_source 頁（大 table——後端砌，前端只 insert；同設定 modal 做法）
        $html = [
            "header" => "<div class='modal__title'>" . $bench_source_name . "</div>",
            "body"   => $model_list_page,
        ];
        
        return sc_response()->json([
            "ok"              => true,
            "bench_source_id" => $bench_source_id,
            "html"            => $html,
        ]);
    }
}
