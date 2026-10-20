<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\action;

// == sc_ai_benchmark request handle_fetch_bench_source：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_benchmark_request 分遷入（one file one action） ==

trait handle_fetch_bench_source
{
    // POST fetch_bench_source：bench_source 清單（model 列表交 fetch_models 逐 bench_source 載入）
    public function handle_fetch_bench_source()
    {
        // 可用 bench_source（聲明——存在＝前台可用；bench_source 清單由此衍生；model 列表交 fetch_models 逐 bench_source 載入）
        $available_bench_source = sc_ai_benchmark()->main()->get_available_bench_source();
        $result = ["ok" => true];
        // bench_source 直接回傳聲明（key 為 bench_source_id——值＝資料袋）
        $result["bench_source"] = $available_bench_source;

        // html：modal tabs（來源切換列）＋body（左側欄＋右內容——後端砌，前端只 insert；同設定 modal 做法）
        $result["html"] = [];
        $result["html"]["tabs"] = $this->build_modal_tabs_html($available_bench_source);
        $result["html"]["modal_body"] = $this->build_modal_body_html($available_bench_source);

        return sc_response()->json($result);
    }
}
