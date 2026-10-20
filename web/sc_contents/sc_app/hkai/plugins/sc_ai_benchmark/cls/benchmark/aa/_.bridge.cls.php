<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa;

// == sc_ai_benchmark benchmark/aa：benchmark 來源本體（bridge 檔——singleton；包內短名 bench_source()；外部入口 sc_ai_benchmark()->instance__aa()） ==
// == 2026-10-07 自 benchmark/swe 複製後改為 Artificial Analysis 來源（官方 API v2 單次抓取；x-api-key 讀 .env〔BENCHMARK_SRC_AA_API_KEY〕） ==
// == 2026-10-07 多 entry（main＋navigation）：main＝共用底、navigation＝逐項增量；逐項 array_replace_recursive(main, item) 合成最終規格（fetch_category＝entry 名同顯示名） ==

class bench_source_aa_Class
{
    use fetch\redownload_bench_source_models;
    use fetch\fetch_rows;
    use build\build_bench_source_file_content;

    // 自身聲明 main 段（共用底：bench_source_name／fetch_url／fetch_method）
    private $bench_source_main = [];

    // 自身聲明 navigation 段（逐項增量：fetch_category／bench_source_file）
    private $bench_source_navigation = [];

    // initial 完成旗標（main 分派後 true——未完成引用功能即報錯）
    private $initial_done = false;

    // 唔攔白名單（未 initial 都要扑得到——初始化入口 initial；白名單＝__call 即放行）
    private $not_intercepted_methods = [
        "initial",
    ];
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

    // 接收自身聲明（main＋navigation——main initial 分派；對應本 bench_source 嗰份；本體只收值）
    private function initial($bench_source_item)
    {
        $this->bench_source_main       = $bench_source_item["main"];
        $this->bench_source_navigation = $bench_source_item["navigation"];
        $this->initial_done  = true;
    }

    // 前閘（__call）：外邊扑入一律經此——白名單唔攔；其餘未 initial 即回錯誤、完成放行
    public function __call($method_name, $arguments)
    {
        // 白名單（唔攔——未 initial 都要扑得到；例：初始化入口 initial）
        if (in_array($method_name, $this->not_intercepted_methods)) {
            // 白名單都要存在先轉（打錯名＝無限回捲——唔存在掉落下面常規檢查）
            if (method_exists($this, $method_name)) {
                return $this->$method_name(...$arguments);
            }
        }
        // 未 initial → 即刻回錯誤（唔放行）
        if (!$this->initial_done) {
            return ["ok" => false, "error" => "initial 未完成（aa）"];
        }
        // 根本冇呢支 → 唔可以轉（防無限回捲）
        if (!method_exists($this, $method_name)) {
            return ["ok" => false, "error" => "unknown method: " . $method_name];
        }
        // 放行（class 內部 scope——扑到 private 真方法）
        return $this->$method_name(...$arguments);
    }

    // 逐 navigation item 合併 main（array_replace_recursive——item 換／補 main）→ 最終規格列（entry 名＝fetch_category）
    private function get_bench_source_navigation_items()
    {
        $navigation_items = [];

        // 逐項合併（main 做底——item 增量後蓋）
        foreach ($this->bench_source_navigation as $navigation_item) {
            $navigation_items[] = array_replace_recursive($this->bench_source_main, $navigation_item);
        }

        return $navigation_items;
    }

    /**
     * repo 子域（bench_source 檔讀取——cls/benchmark/aa/repo）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa\repo\repo_Class
     */
    public function repo()
    {
        return repo\repo_Class::getInstance();
    }

    /**
     * view 子域（bench_source 頁顯示——cls/benchmark/aa/view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa\view\view_Class
     */
    public function view()
    {
        return view\view_Class::getInstance();
    }
}
