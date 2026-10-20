<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\main;

// == sc_ai_benchmark main：main 域本體（bridge 檔——singleton；呼叫形 sc_ai_benchmark()->main()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat sc_ai_benchmark_main 合成） ==
// == 2026-10-06 repo 分層：bench_source 檔讀寫遷 repo 域（sc_ai_benchmark()->repo()） ==
// == 2026-10-06 config／bench_source 系（get_config／initial）併回本體 ==
// == 2026-10-06 bench_source 實例解析入口 get_bench_source_instance（動態分派＋安全檢查——repo 域分派用） ==

class main_Class
{
    private $config = [];
    private $available_bench_source = [];

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}

    public function get_config($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    // 注入可用 bench_source 聲明（plugin __init__.php——集中於入口提供；class 只收值）
    public function initial($configs)
    {
        $available_bench_source = $configs["available_bench_source"];
        $this->available_bench_source = $available_bench_source;

        // 逐個 bench_source 將對應聲明寫入 bench_source 例項（main 分派——對面包各自接收）
        foreach ($available_bench_source as $bench_source_id => $bench_source_item) {
            // bench_source 例項（解析入口——accessor 唔存在時守衛喺 get_bench_source_instance 內）
            $bench_source_instance = $this->get_bench_source_instance($bench_source_id);

            // 判定：冇對應例項（聲明先行、實作未接——例 swe）→ 跳過分派（前台照列出、功能返 400）
            if ($bench_source_instance === null) {
                continue;
            }
            $bench_source_instance->initial($bench_source_item);
        }
    }

    // 可用 bench_source 聲明（bench_source_id → 名稱——__init__ 注入；存在＝前台可用）
    public function get_available_bench_source()
    {
        return $this->available_bench_source;
    }

    // bench_source 實例解析入口（bench_source_id → 例項——動態分派；repo 域分派用）
    public function get_bench_source_instance($bench_source_id)
    {
        // 具名分派方法名（instance__{bench_source_id}——對應 sc_ai_benchmark_Class 嘅 instance__arena）
        $instance_method_name = "instance__" . $bench_source_id;

        // 安全檢查：具名分派方法未定義（唔喺 sc_ai_benchmark_Class）→ 唔呼叫、回 null
        if (!method_exists(sc_ai_benchmark(), $instance_method_name)) {
            return null;
        }

        // 動態分派（跳轉目標：
        //   1. "arena" → sc_ai_benchmark()->instance__arena()（cls/benchmark/arena/bench_source_arena_Class）
        // ）
        return sc_ai_benchmark()->{$instance_method_name}();
    }
}
