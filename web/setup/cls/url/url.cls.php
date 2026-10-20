<?php

namespace sc\setup\url;

// == HKAI setup url：url 域本體（url.cls.php——singleton；呼叫形 url()->get_segment(0)）
// == 內容：class（singleton）＋請求網址段解析——REQUEST_URI → 段清單（0 起算——get_segment(0) 即首段）；＋磁碟路徑 → URL（sc_to_web_path——去站根前綴；統一入口 setup()->url()）
// == 語法下限：PHP 5.4（同 load.php）

class url_Class
{
    private static $instance = null;

    // 請求網址段清單（建構時解析一次——同一請求共用）
    private $request_path_segments;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\url\url_Class
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->request_path_segments = $this->read_request_path_segments();
    }

    /**
     * 網址段（0 起算——get_segment(0) ＝首段；唔存在＝空字串）
     *
     * @param int $segment_index 段序（0 起算）
     * @return string
     */
    public function get_segment($segment_index)
    {
        // 判定：冇嗰段 → 空字串
        if (!isset($this->request_path_segments[$segment_index])) {
            return "";
        }

        return $this->request_path_segments[$segment_index];
    }

    // 讀請求網址段清單（REQUEST_URI 去 query → 去網址基準 → 爆開；空段剔走）
    private function read_request_path_segments()
    {
        // 請求路徑（去 query；冇 query 或冇 REQUEST_URI ＝空）
        $request_uri_path = isset($_SERVER["REQUEST_URI"]) ? (string) parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) : "";

        // 網址基準（web 目錄嘅 URL path——例：/web；由本檔磁碟位置推：cls/url → cls → setup → web）
        $web_url_base = "/" . basename(dirname(dirname(dirname(__DIR__))));

        // 相對路徑（有網址基準前綴 → 去佢；無 → 原樣）
        $request_relative_path = $request_uri_path;

        // 判定：有基準前綴 → 去基準
        if (strpos($request_uri_path, $web_url_base . "/") === 0) {
            $request_relative_path = substr($request_uri_path, strlen($web_url_base));
        }

        // 網址段清單（空段剔走）
        $request_path_segments = array_values(array_filter(explode("/", $request_relative_path), "strlen"));

        return $request_path_segments;
    }

    // 磁碟路徑 → URL（去站根前綴——瀏覽器可用；空＝false；唔喺站根之下＝原樣）
    public function sc_to_web_path($filepath)
    {
        if (!$filepath) {
            return false;
        }

        // 正規化（統一正斜線＋解析 .／..——磁碟真路徑；唔存在＝照原樣）
        $filepath  = str_replace("\\", "/", $filepath);
        $real_path = realpath($filepath);

        // 判定：解析得到 → 用真路徑
        if ($real_path !== false) {
            $filepath = str_replace("\\", "/", $real_path);
        }

        // 站根（單一來源＝sc_config/load.php repo_root）
        $repo_root = sc_get_config("repo_root");

        $site_root_path = realpath($repo_root);
        $site_root      = str_replace("\\", "/", $site_root_path);

        // 判定：喺站根之下 → 去站根前綴即 URL 路徑
        if (strpos($filepath, $site_root) === 0) {
            $site_root_length = strlen($site_root);

            return substr($filepath, $site_root_length);
        }

        // 唔喺站根之下（如外部 URL）→ 原樣
        return $filepath;
    }
}
