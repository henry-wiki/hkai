<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\action;

// == sc_ai_benchmark request handle_fetch_bench_source_navigation：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-10-07 新（撳來源 tab → 重砌左欄 entry 掣；來源唔使實例——聲明驅動） ==

trait handle_fetch_bench_source_navigation
{
    // POST fetch_bench_source_navigation：bench_source_id 指定——回該來源左欄 entry 掣 HTML（前端換入 #side_bench_source_list）
    public function handle_fetch_bench_source_navigation()
    {
        $input_data = sc_request()->json();
        $bench_source_id = isset($input_data["bench_source_id"]) ? $input_data["bench_source_id"] : "";

        // 可用 bench_source 聲明（唔喺聲明 → 400）
        $available_bench_source = sc_ai_benchmark()->main()->get_available_bench_source();
        if (!isset($available_bench_source[$bench_source_id])) {
            return sc_response()->json(["ok" => false, "error" => "unknown bench_source: " . $bench_source_id], 400);
        }

        // html：左欄 entry 掣（後端砌，前端只 insert）
        $html = [
            "navigation" => $this->build_navigation_html($bench_source_id),
        ];

        return sc_response()->json([
            "ok"              => true,
            "bench_source_id" => $bench_source_id,
            "html"            => $html,
        ]);
    }
}
