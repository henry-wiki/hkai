<?php

namespace sc\setup\request;

// == HKAI setup request：request 域本體（域本體——singleton；呼叫形 setup()->request()->fetch_section()）
// == 內容：class（singleton）＋五個入站動作（trait——一檔一動作）由本檔頂 require；共用回應＝本體 respond_json（私助）
// == 動作：五支＝fetch_section／start_single_check／start_single_install／switch_language／record_section（本目錄——五個 trait 一檔一動作）
// == 語法下限：PHP 5.4（同 load.php）

// 載入五個入站動作 trait（一檔一動作——trait 先過 class）
require_once __DIR__ . "/fetch_section.php";
require_once __DIR__ . "/start_single_check.php";
require_once __DIR__ . "/start_single_install.php";
require_once __DIR__ . "/switch_language.php";
require_once __DIR__ . "/record_section.php";

class request_Class
{
    use fetch_section;
    use start_single_check;
    use start_single_install;
    use switch_language;
    use record_section;

    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\request\request_Class
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
    }

    // 共用回應（五個入站動作收工——出 JSON（帶 status）＋收尾）
    public function respond_json($result, $status_code)
    {
        header("Content-Type: application/json; charset=utf-8");

        // 判定：要自訂 status → 設返
        if ($status_code !== 200) {
            http_response_code($status_code);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
