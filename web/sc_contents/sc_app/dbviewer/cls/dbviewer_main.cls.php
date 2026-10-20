<?php

namespace sc\app\dbviewer\cls;

// == sc_app/dbviewer/cls/dbviewer_main.cls.php：dbviewer app 層主 class（singleton——bridging dbviewer__main()） ==
// == 職責：只做「接上原版」三件事——自啟 session、以初始化檔寫死的庫路徑補齊 GET 連線參數、seed 免登入 session ==
// == 畫面與操作全部源用原版 adminer.php（不改一行、零定制外觀） ==

class dbviewer_main_Class
{
    private static $instance = null;
    
    // 原版 session 鍵常數（sqlite 模式：DRIVER 固定、SERVER 即 ?sqlite 參數=庫檔路徑、使用者名稱固定）
    const DRIVER   = "sqlite";
    const USERNAME = "dbviewer";
    const PASSWORD = "";
    
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {}
    
    // 執行入口（index.php 呼叫——回傳字串走框架 prepare_response 四態）
    public function run()
    {
        $sqlite_file = dbviewer__core()->get_sqlite_file();
        
        // 本 app 走 api 組（無 session middleware）→ 自啟 Adminer 同名 session（原版見 SID 已定義即不再自啟）
        $this->start_session();
        
        // 以寫死的庫路徑補齊連線參數（URL 已帶則不覆蓋——原版以 GET 參數定 driver/server/username/db）
        $this->fill_connection_params($sqlite_file);
        
        // seed 免登入 session（結構與原版 POST 登入後一致）
        $this->seed_login_session($sqlite_file);
        
        // 載入定制掛鉤（adminer_object()——必須在原版執行前定義）
        require_once __DIR__ . "/../externals/adminer/dbviewer_adminer.php";

        // exit 分支補拆（select 等分支喺 adminer 內部 page_headers() 送 deny 後即 exit——run() 尾嗰行 header_remove 執行唔到）
        // header_register_callback：PHP 即將發出 headers 前一刻先回呼——deny 屆時仲喺 mbuffer，照拆得走（exit／非 exit 分支共通）
        header_register_callback(function () {
            header_remove("X-Frame-Options");
        });
        
        // 執行原版 Adminer（內部直接 echo＋部分分支自行 exit／header）
        // ob 回呼形：select 等分支喺 adminer 內部 exit——普通 ob_get_clean 執行唔到；回呼由 PHP 於輸出刷出時（含 exit 觸發）呼叫，顯示改寫必定生效
        ob_start(function ($adminer_html) use ($sqlite_file) {
            return $this->shorten_db_path_display($adminer_html, $sqlite_file);
        });
        require __DIR__ . "/../externals/adminer/adminer.php";
        $adminer_html = ob_get_clean();

        // 非 exit 分支：ob_get_clean 有行到——照樣改寫一次（回呼已處理則為冪等，路徑已唔存在＝零影響）
        // （exit 分支 $adminer_html 為空字串，回呼已於刷出時完成改寫）
        if ($adminer_html !== "") {
            $adminer_html = $this->shorten_db_path_display($adminer_html, $sqlite_file);
        }

        return $adminer_html;
    }

    // 顯示層收攏：sqlite 庫路徑（= Adminer 嘅 DB 名）喺文字節點（Breadcrumb／<title>／庫清單）→ 只顯示檔名
    // 逐個「>文字<」節點內替換——href／表單 value 內嘅路徑原樣保留（點擊連結唔會斷）；不改第三方 adminer.php
    private function shorten_db_path_display($adminer_html, $sqlite_file)
    {
        // 三個路徑寫法（原值／正斜杠／反斜杠）變體，全部映到純檔名
        $display = basename($sqlite_file);
        $slash_form = str_replace("\\", "/", $sqlite_file);
        $bs_form = str_replace("/", "\\", $sqlite_file);

        $adminer_html = preg_replace_callback(
            "/>([^<]+)</",
            function ($text_node) use ($sqlite_file, $slash_form, $bs_form, $display) {
                // 三個路徑寫法一律換檔名顯示
                $node_text = str_replace($sqlite_file, $display, $text_node[1]);
                $node_text = str_replace($slash_form, $display, $node_text);
                $node_text = str_replace($bs_form, $display, $node_text);

                return ">" . $node_text . "<";
            },
            $adminer_html
        );

        return $adminer_html;
    }
    
    // 啟動 Adminer 專用 session（名稱與原版一致 adminer_sid——與框架 session 分開、互不干擾）
    private function start_session()
    {
        // 已有啟用 session（其他 middleware 先啟過）→ 不重複啟動
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        session_name("adminer_sid");
        session_start();
    }
    
    // 補齊原版連線三參數（缺啥補啥：?sqlite=庫路徑（原版 SERVER）、?username、?db=預選庫——帶 db 即直入表清單）
    private function fill_connection_params($sqlite_file)
    {
        // sqlite 模式庫路徑（原版 ?sqlite 參數）
        if (!isset($_GET["sqlite"])) {
            $_GET["sqlite"] = $sqlite_file;
        }
        
        // 使用者名稱（原版 ?username 參數——與 seed session 的鍵一致）
        if (!isset($_GET["username"])) {
            $_GET["username"] = self::USERNAME;
        }
        
        // 預選庫（原版 ?db 參數）
        if (!isset($_GET["db"])) {
            $_GET["db"] = $sqlite_file;
        }
    }
    
    // 預置已登入 session（原版判別式：pwds 值為字串才放行連線；db 清單＝左欄可選庫）
    private function seed_login_session($sqlite_file)
    {
        $_SESSION["pwds"][self::DRIVER][$sqlite_file][self::USERNAME]                = self::PASSWORD;
        $_SESSION["db"][self::DRIVER][$sqlite_file][self::USERNAME][$sqlite_file]    = true;
    }
}
