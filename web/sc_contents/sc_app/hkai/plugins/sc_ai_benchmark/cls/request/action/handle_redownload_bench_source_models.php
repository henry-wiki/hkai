<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\action;

// == sc_ai_benchmark request handle_redownload_bench_source_models：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_benchmark_request 分遷入（one file one action） ==

trait handle_redownload_bench_source_models
{
    // POST redownload_bench_source_models：bench_source_id 必填——單一 bench_source 重新下載（具名分派——arena 包內 redownload_bench_source_models）
    public function handle_redownload_bench_source_models()
    {
        // 讀入 bench_source_id（JSON body——必填）
        $input_data = sc_request()->json();
        $bench_source_id = isset($input_data["bench_source_id"]) ? $input_data["bench_source_id"] : "";
        
        // bench_source_id 必填（缺失 → 400）
        if ($bench_source_id === "") {
            return sc_response()->json(["ok" => false, "error" => "bench_source_id required"], 400);
        }
        
        // 重新下載（具名分派——逐源直接跳；F12 單一 target；數據由包內自取）
        if ($bench_source_id === "arena") {
            $result = sc_ai_benchmark()->instance__arena()->redownload_bench_source_models();
        }
        // 具名分派（swe——同 arena 形）
        elseif ($bench_source_id === "swe") {
            $result = sc_ai_benchmark()->instance__swe()->redownload_bench_source_models();
        }
        // 具名分派（aa——同 swe 形）
        elseif ($bench_source_id === "aa") {
            $result = sc_ai_benchmark()->instance__aa()->redownload_bench_source_models();
        }
        // 具名分派（llm_stats——同 aa 形）
        elseif ($bench_source_id === "llm_stats") {
            $result = sc_ai_benchmark()->instance__llm_stats()->redownload_bench_source_models();
        } else {
            // 無對應 bench_source（唔係 arena）→ 400（前端顯示失敗）
            return sc_response()->json(["ok" => false, "error" => "unknown bench_source: " . $bench_source_id], 400);
        }
        
        return sc_response()->json(["ok" => true, "results" => [$bench_source_id => $result]]);
    }
}
