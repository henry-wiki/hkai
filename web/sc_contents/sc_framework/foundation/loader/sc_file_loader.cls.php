<?php
namespace sc\framework\foundation\loader;
// sc_file_loader：目錄載入工具（遞迴 include + 過濾）——移自 sc_includes/bootstrap.php
// 語意：load($file, $opt, $deep)——單一方法涵蓋檔案／目錄／遞迴下探
// 註：原名 sc_bootstrap（名不副實——純載入器、非引導器）；命名空間 sc\framework\foundation\loader——一律經 sc_file_loader() bridging 取單例

class sc_file_loader_Class
{
    private static $instance = null;
    
    private $allowed_opts     = ['end','contains','re'];
    private $max_include_deep = 9;
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
    }
    
    // 解析過濾條件：回傳 [type, value]；空 opt 視為 end:.php（只載 .php）
    private function parse_filter_opt($opt)
    {
        // 載入器自身早過 function 樹載入——原生 trim（叫 sc_trim＝未載 Fatal）
        $opt = trim($opt ?? "");
        
        // 空 opt：預設 end:.php（等同「只載 .php 檔」）
        if (!$opt) {
            return ["end", ".php"];
        }
        
        $opt_parts = explode(":", $opt, 2);
        
        // 無冒號：視為 end（字尾過濾）
        if (count($opt_parts) === 1) {
            return ["end", $opt_parts[0]];
        }
        
        // 未知 type：立即報錯（明確勝於隱藏）
        if (!in_array($opt_parts[0], $this->allowed_opts)) {
            sc_trigger_error("sc_file_loader : unknown opt type : [".$opt_parts[0]."]");
        }
        
        return [$opt_parts[0], $opt_parts[1]];
    }
    
    public function load($file, $opt = null, $max_deep = null, $deep = 0)
    {
        // 掃描取得命中檔案清單（掃描/過濾/深度邏輯集中在 find——load 只負責 include）
        $matched_files = $this->find($file, $opt, $max_deep, $deep);
        
        // 逐一載入命中檔
        foreach ($matched_files as $matched_file) {
            include_once($matched_file);
        }
    }
    
    public function load_func($file,$max_deep = null)
    {
        $this->load($file,".func.php",$max_deep);
    }
    
    // 掃描目錄（依過濾條件）→ 回傳命中檔案路徑 array（不 include——呼叫端自行 require）
    // 與 load() 相同掃描/過濾邏輯（end/contains/re＋深度護欄），只收集回傳
    public function find($file, $opt = null, $max_deep = null, $deep = 0)
    {
        // 預設不限深（保留舊行為——以 9 層護欄兜底）
        if ($max_deep === null) {
            $max_deep = $this->max_include_deep;
        }
        
        $file = rtrim($file, "/");
        
        // 傳入的是檔案 → 直接回傳該檔（不受 deep 限制）
        if (is_file($file)) {
            return [$file];
        }
        
        // 深度護欄：超過上限（max_deep）即停止下探
        if ($deep > $max_deep) {
            return [];
        }
        
        if (!is_dir($file)) {
            return [];
        }
        
        // 過濾條件解析（與 load 同款）
        [$filter_type, $filter_value] = $this->parse_filter_opt($opt);
        
        $matched_files   = [];
        $collected_files = [];
        $collected_dirs  = [];
        
        // 掃描目錄（scan_dir：per-request 記憶＋跨請求快取——以目錄 mtime 驗證）
        $scan_result     = $this->scan_dir($file);
        $collected_files = $scan_result["files"];
        $collected_dirs  = $scan_result["dirs"];
        
        // 過濾命中才收集
        foreach ($collected_files as $collect_file) {
            $collect_file_name = basename($collect_file);
            
            if ($filter_type === "end") {
                $is_filtered = substr($collect_file_name, -strlen($filter_value)) !== $filter_value;
            } elseif ($filter_type === "contains") {
                $is_filtered = strpos($collect_file_name, $filter_value) === false;
            } else {
                $is_filtered = preg_match($filter_value, $collect_file_name) !== 1;
            }
            if ($is_filtered) {
                continue;
            }
            
            $matched_files[] = $collect_file;
        }
        
        // 子目錄遞迴下探（deep 遞增、max_deep 透傳）
        foreach ($collected_dirs as $sub_dir) {
            $matched_files = array_merge($matched_files, $this->find($sub_dir, $opt, $max_deep, $deep + 1));
        }
        
        return $matched_files;
    }

    //---------------------------------------------------------
    // 目錄掃描快取（scan_dir）
    // 1. per-request 記憶（同一目錄只掃一次）
    // 2. 跨請求檔案快取（sc_storage/cache/file_loader_scan.json）
    // 3. 以目錄 mtime 驗證——目錄內容有加／刪檔即失效重掃（語義與逐次 scandir 完全一致）
    //---------------------------------------------------------
    private static $scan_memory     = null;
    private static $scan_dirty      = false;
    private static $scan_cache_file = null;

    private function scan_dir($dir)
    {
        // 判定：未初始化 → 載入跨請求快取（一次）
        if (self::$scan_memory === null) {
            $this->load_scan_cache();
        }

        $dir_mtime = @filemtime($dir);

        // 判定：有快取且目錄 mtime 未變 → 直接回傳（免 scandir＋免逐項 is_dir）
        if (isset(self::$scan_memory[$dir])) {
            if (self::$scan_memory[$dir]["mtime"] === $dir_mtime) {
                return self::$scan_memory[$dir];
            }
        }

        $collected_files = [];
        $collected_dirs  = [];
        $dir_entries     = @scandir($dir);

        // 判定：目錄讀唔到 → 當空（同舊行為）
        if (!is_array($dir_entries)) {
            $dir_entries = [];
        }

        // 逐項分類（檔案／子目錄）
        foreach ($dir_entries as $entry) {
            // 判定：. 與 .. → 跳過
            if ($entry === "." || $entry === "..") {
                continue;
            }

            $entry_path = $dir . "/" . $entry;

            // 判定：子目錄 → 收入 dirs；否則收入 files
            if (is_dir($entry_path)) {
                $collected_dirs[] = $entry_path;
            } else {
                $collected_files[] = $entry_path;
            }
        }

        self::$scan_memory[$dir] = [
            "mtime" => $dir_mtime,
            "files" => $collected_files,
            "dirs"  => $collected_dirs,
        ];
        self::$scan_dirty = true;

        return self::$scan_memory[$dir];
    }

    private function load_scan_cache()
    {
        self::$scan_memory     = [];
        self::$scan_cache_file = SC_DIR_STORAGE . "/cache/file_loader_scan.json";

        // 判定：快取檔存在 → 讀入（壞檔／空檔＝當冇）
        if (is_file(self::$scan_cache_file)) {
            $cache_json = @file_get_contents(self::$scan_cache_file);
            $cache_data = json_decode((string)$cache_json, true);

            // 判定：合法 array → 採用
            if (is_array($cache_data)) {
                self::$scan_memory = $cache_data;
            }
        }

        register_shutdown_function([$this, "save_scan_cache"]);
    }

    // 請求收尾：有掃描變更 → 一次性寫回快取檔
    public function save_scan_cache()
    {
        // 判定：冇變更 → 唔寫
        if (!self::$scan_dirty) {
            return;
        }

        $cache_dir = dirname((string)self::$scan_cache_file);

        // 判定：cache 目錄唔存在 → 建立
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }

        $cache_json = json_encode(self::$scan_memory, JSON_UNESCAPED_SLASHES);
        @file_put_contents((string)self::$scan_cache_file, (string)$cache_json, LOCK_EX);
        self::$scan_dirty = false;
    }
    
}
