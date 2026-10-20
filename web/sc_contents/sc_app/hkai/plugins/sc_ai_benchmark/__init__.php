<?php

// == sc_ai_benchmark plugin：bench_source 重生＋模型選擇資料來源 ==
// == 職責：註冊 plugin（asset css＋4 個 request action）＋可用 bench_source 聲明（main->initial） ==

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, [
    // plugin settings
    "asset" => [
        __DIR__ . "/asset/sc_ai_benchmark.css",
    ],
]);
$plugin->setEnabled(true);

// bench_source 資料根（storage——來源檔＋bak 統一住 benchmark/model_store，唔住插件目錄）
$benchmark_model_store_dir = $controller->get_storage_dir() . "/benchmark/model_store";

// 注入可用 bench_source 聲明（存在＝前台可用——bench_source id 為 key；值＝main 共用底＋navigation 逐項增量（fetch_category＋bench_source_file 各自 store 檔＋fetch_query；item 可覆寫 main 任何鍵——例 fetch_url／fetch_shape；fetch 時逐項 array_replace_recursive(main, item) 合成最終規格），將來可加）
sc_ai_benchmark()->main()->initial([
    "available_bench_source" => [
        "arena" => [
            "main" => [
                "bench_source_name" => "Arena",
                
                
                "fetch_url"         => "https://datasets-server.huggingface.co/rows",
                "fetch_method"      => "get",
                
                "fetch_query"       => [
                    "dataset" => "lmarena-ai/leaderboard-dataset",
                    
                    "split"   => "latest",
                ],
                
                "fetch_paging"      => [
                    "offset_key"  => "offset",
                    "length_key"  => "length",
                    "page_length" => 100,
                    "max_rows"    => 100,
                    "sleep_secs"  => 1,
                ],
                
            ]
            ,"navigation"=> [
                [
                    "fetch_category"    => "overall",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_arena__overall.php",
                    "fetch_query"       => [
                        
                        "config"  => "text",
                        
                    ],
                    
                ]
                ,[
                    "fetch_category"    => "agent",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_arena__agent.php",
                    
                    "fetch_query"       => [
                        
                        "config"  => "agent",
                        
                    ],
                ]
                ,[
                    "fetch_category"    => "code",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_arena__code.php",
                    "fetch_query"       => [
                        
                        "config"  => "webdev",
                        
                    ],
                ]
                ,[
                    "fetch_category"    => "image",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_arena__image.php",
                    "fetch_query"       => [
                        
                        "config"  => "text_to_image",
                        
                    ],
                ]
                ,[
                    "fetch_category"    => "video",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_arena__video.php",
                    "fetch_query"       => [
                        
                        "config"  => "text_to_video",
                        
                    ],
                ]
            ]
        ],
        "swe" => [
            "main" => [
                "bench_source_name" => "SWE",
                
                "fetch_url"         => "https://raw.githubusercontent.com/SWE-bench/swe-bench.github.io/master/data/leaderboards.json",
                "fetch_method"      => "get",
            ],
            "navigation" => [
                [
                    "fetch_category"    => "multimodal",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_swe__multimodal.php",
                    "fetch_query"       => [],
                ],
                [
                    "fetch_category"    => "verified",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_swe__verified.php",
                    "fetch_query"       => [],
                ],
                [
                    "fetch_category"    => "lite",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_swe__lite.php",
                    "fetch_query"       => [],
                ],
                [
                    "fetch_category"    => "test",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_swe__test.php",
                    "fetch_query"       => [],
                ],
                [
                    "fetch_category"    => "multilingual",
                    
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_swe__multilingual.php",
                    "fetch_query"       => [],
                ],
            ],
        ],
        "aa" => [
            "main" => [
                "bench_source_name" => "Artificial Analysis",
                "fetch_url"         => "https://artificialanalysis.ai/api/v2/data/llms/models",
                "fetch_method"      => "get",
                "api_key"           => hkai__sc_oauth_keys()->get_key("BENCHMARK_SRC_AA_API_KEY"),
            ],
            "navigation" => [
                [
                    "fetch_category"    => "overall",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_aa__overall.php",
                    "score_field"       => "artificial_analysis_intelligence_index",
                    "score_label"       => "Intelligence Index",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "coding",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_aa__coding.php",
                    "score_field"       => "artificial_analysis_coding_index",
                    "score_label"       => "Coding Index",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "math",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_aa__math.php",
                    "score_field"       => "artificial_analysis_math_index",
                    "score_label"       => "Math Index",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "reasoning",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_aa__reasoning.php",
                    "score_field"       => "hle",
                    "score_label"       => "HLE",
                    "score_format"      => "percent",
                ],
                [
                    "fetch_category"    => "agentic",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_aa__agentic.php",
                    "score_field"       => "tau2",
                    "score_label"       => "Tau2-Bench",
                    "score_format"      => "percent",
                ],
            ],
        ],
        "llm_stats" => [
            "main" => [
                "bench_source_name" => "LLM Stats",
                "fetch_url"         => "https://api.llm-stats.com/stats/v1/models",
                "fetch_method"      => "get",
                "fetch_shape"       => "models",
                "api_key"           => hkai__sc_oauth_keys()->get_key("BENCHMARK_SRC_LLM_STATS_API_KEY"),
                "fetch_paging"      => [
                    "length_key"     => "limit",
                    "page_length"    => 200,
                    "cursor_key"     => "cursor",
                    "max_page_count" => 3,
                    "sleep_secs"     => 1,
                ],
            ],
            "navigation" => [
                [
                    "fetch_category"    => "general",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_llm_stats__general.php",
                    "fetch_url"         => "https://api.llm-stats.com/leaderboard/indexes/all",
                    "fetch_shape"       => "index",
                    "score_field"       => "general",
                    "score_label"       => "General",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "reasoning",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_llm_stats__reasoning.php",
                    "fetch_url"         => "https://api.llm-stats.com/leaderboard/indexes/all",
                    "fetch_shape"       => "index",
                    "score_field"       => "reasoning",
                    "score_label"       => "Reasoning",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "code",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_llm_stats__code.php",
                    "fetch_url"         => "https://api.llm-stats.com/leaderboard/indexes/all",
                    "fetch_shape"       => "index",
                    "score_field"       => "code",
                    "score_label"       => "Code",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "math",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_llm_stats__math.php",
                    "fetch_url"         => "https://api.llm-stats.com/leaderboard/indexes/all",
                    "fetch_shape"       => "index",
                    "score_field"       => "math",
                    "score_label"       => "Math",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "tool_calling",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_llm_stats__tool_calling.php",
                    "fetch_url"         => "https://api.llm-stats.com/leaderboard/indexes/all",
                    "fetch_shape"       => "index",
                    "score_field"       => "tool_calling",
                    "score_label"       => "Tool Calling",
                    "score_format"      => "number",
                ],
                [
                    "fetch_category"    => "vision",
                    "bench_source_file" => $benchmark_model_store_dir . "/bench_source_llm_stats__vision.php",
                    "fetch_url"         => "https://api.llm-stats.com/leaderboard/indexes/all",
                    "fetch_shape"       => "index",
                    "score_field"       => "vision",
                    "score_label"       => "Vision",
                    "score_format"      => "number",
                ],
            ],
        ],
    ],
]);

$plugin->add_request_action("benchmark/redownload_bench_source_models", __DIR__ . "/request/redownload_bench_source_models.php");

// 註冊 request action（model selection——modal 資料來源）
$plugin->add_request_action("benchmark/fetch_bench_source", __DIR__ . "/request/fetch_bench_source.php");

// 註冊 request action（單一 bench_source model 列表——撳左欄導航逐個載入）
$plugin->add_request_action("benchmark/fetch_models", __DIR__ . "/request/fetch_models.php");

// 註冊 request action（來源 tab 重砌左欄——撳 modal__tabs 掣）
$plugin->add_request_action("benchmark/fetch_bench_source_navigation", __DIR__ . "/request/fetch_bench_source_navigation.php");
