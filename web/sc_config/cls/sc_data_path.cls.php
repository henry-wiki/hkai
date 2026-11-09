<?php

namespace sc\config;

// ============================================================================
// sc_data_path.cls.php — 資料路徑總管（<data_dir> 之下所有路徑唯一來源）
// 上游：init.php（呼叫 init()）；load.php 配置陣列用 get_ 取值
// 下游：sc_get_env()（.env 值）；零寫檔
// 規則：有 DATA_BASE_DIR＋APP_VENDOR＋APP_NAME → {base}/{vendor}/{app}/data{頻道}；否則站台根＋DATA_DIR（預設 data）
// ============================================================================

class sc_data_path_Class
{
    private static $instance = null;

    // 解析後資料根（首次 get_data_dir() 時計算）
    private string $data_dir = "";


    // 基礎根（init() 讀 DATA_BASE_DIR；null／空＝未設定）
    private ?string $data_base_dir = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct()
    {
       
    }

    /**
     * 初始化：解析資料根（init.php 入口呼叫一次）。
     *
     * 上游：init.php
     * 下游：get_data_dir()
     */
    public function init(): void
    {
        // 基礎根：啟動者傳入（空／null＝未設定）
        $data_base_dir = getenv("DATA_BASE_DIR");

        $this->data_base_dir = trim((string)$data_base_dir);

        $this->data_dir = "";
        
        // 預熱：入口一次解析，之後 get_* 直接用快取
        $this->get_data_dir();
    }

    /**
     * 資料根（<data_dir>；首次呼叫時解析並快取）。
     *
     * 上游：init()、所有路徑方法
     * 下游：sc_get_env()、resolve_host_suffix()
     */
    public function get_data_dir(): string
    {
        if($this->data_dir)
        {
            return $this->data_dir;
        }
        
        $data_base_dir = $this->data_base_dir;

        $app_vendor = sc_get_env("APP_VENDOR", "");
        $app_name   = sc_get_env("APP_NAME", "");

        $data_dirs = [];
       
        if($data_base_dir && $app_vendor && $app_name)
        {
            $data_dirs[] = $data_base_dir;
            $data_dirs[] = $app_vendor;
            $data_dirs[] = $app_name;
            $data_dirs[] = "data" . $this->resolve_host_suffix();

        }
        else 
        {
            $data_dirs[] = SC_REPO_ROOT;
            $data_dirs[] = trim((string)sc_get_env("DATA_DIR", "")) ?: "data";
        }
        
        $data_dirs = implode("/",$data_dirs);
        $data_dirs = str_replace("\\","/",$data_dirs);
       
        $this->data_dir = rtrim($data_dirs,"/");
        
        return $this->data_dir;
    }

    /**
     * 頻道後綴：host 以 .dev.internal 結尾＝-dev；.staging.internal 結尾＝-staging；其餘＝空。
     */
    private function resolve_host_suffix(): string
    {
        // 去埠（HTTP_HOST 帶 :2484——否則尾綴判斷永遠 false）
        $request_host = strtolower((string)($_SERVER["HTTP_HOST"] ?? ""));
        $request_host = explode(":", $request_host)[0];

        // 判定：dev 站
        if (str_ends_with($request_host, ".dev.internal") === true) {
            return "-dev";
        }

        // 判定：staging 站
        if (str_ends_with($request_host, ".staging.internal") === true) {
            return "-staging";
        }

        return "";
    }

    /**
     * 子路徑（{data_dir}/{relative_path}）——所有目錄／檔案唯一入口。
     *
     * 上游：load.php、sc_api_keys、sc_data_override、get_ca_certificate_path
     * 下游：get_data_dir()
     */
    public function get_data_sub_dir(string $relative_path): string
    {
        return $this->get_data_dir() . "/" . $relative_path;
    }
}
