<?php

namespace sc\app\hkai\cls\process\server;

// == server_host_Class：server 位址工具（singleton——經 server()->host() 取得）
// == 職責：位址解析（未帶 host＝127.0.0.1；未帶 port＝交 server_port 抽可用埠）＋origin 白名單（http/https × apex/www 變體，去重寫檔）
// == 上游：server_Class::start_server()
// == 下游：server_port（可用埠）、sc_config（bridge_dir）、檔案系統（origin.whitelist）

class server_host_Class
{
    private static $instance = null;
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

    // 埠工具（可用埠抽選／等釋放）
    private function port()
    {
        return server_port_Class::getInstance();
    }

    // 位址解析（未帶 host → 127.0.0.1；未帶 port → 自動揀可用埠；揀唔到 → false）
    public function resolve_server_address($server_host, $server_port)
    {
        $server_host = trim($server_host);
        $server_port = trim((string)$server_port);

        // 自動模式：未指定 host → 本機
        if ($server_host === "") {
            $server_host = "127.0.0.1";
        }

        // 判定：未指定 port → 由 port 域抽可用埠
        if ($server_port === "") {
            $available_port = $this->port()->find_available_port();

            // 判定：抽唔到可用埠（0＝9 次全撞）→ false
            if ((int)$available_port === 0) {
                return false;
            }

            $server_port = (string)$available_port;
        }

        return ["host" => $server_host, "port" => (int)$server_port];
    }

    // 以本次請求來源重寫 origin 白名單（整份覆寫——清走舊埠／舊主機；補齊 http/https × apex/www）
    public function write_origin_whitelist()
    {
        $origin = trim((string)($_SERVER["HTTP_ORIGIN"] ?? ""));

        // 判定：冇 Origin 標頭 → 由請求 host 組（本機 AJAX 可能無 Origin）
        if ($origin === "") {
            $request_host = trim((string)($_SERVER["HTTP_HOST"] ?? ""));

            // 判定：連 host 都冇（CLI）→ 唔寫
            if ($request_host === "") {
                return;
            }

            $origin_scheme = "http";

            // 判定：HTTPS 請求 → https
            if (($_SERVER["HTTPS"] ?? "") !== "") {
                $origin_scheme = "https";
            }

            $origin = $origin_scheme . "://" . $request_host;
        }

        $origin_variants = $this->build_origin_variants($origin);

        // 判定：解唔出來源 → 唔寫
        if (count($origin_variants) === 0) {
            return;
        }

        $whitelist_file = sc_get_config("hkai.bridge_dir") . "/origin.whitelist";

        // 判定：白名單目錄唔存在 → 建立
        if (!is_dir(dirname($whitelist_file))) {
            @mkdir(dirname($whitelist_file), 0777, true);
        }

        // 整份覆寫（今次來源以外嘅條目全部清走）
        // *** : 步驟 1——先讀入舊白名單（舊實作整份覆寫，會同其他 origin 互相踢走）
        $existing_lines = [];

        // 判定：舊白名單存在 → 讀入（讀唔到就當空）
        if (is_file($whitelist_file) === true) {
            $existing_lines = @file($whitelist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }

        $whitelist_entries = $this->collect_whitelist_entries($existing_lines, $origin_variants);

        // *** : 步驟 2——寫回（既有 ∪ 本次 origin ∪ 本機萬用），CRLF 結尾
        @file_put_contents($whitelist_file, implode("\r\n", array_keys($whitelist_entries)) . "\r\n", LOCK_EX);
    }

    // 砌來源變體（domain → http/https × apex/www 四條；IP／localhost／單段名／IPv6 → http/https 兩條）
    // 合併白名單：既有條目 ∪ 本次 origin ∪ 本機萬用（埠每次隨機，故寫 http://127.0.0.1:*）
    private function collect_whitelist_entries($existing_lines, $origin_variants)
    {
        $whitelist_entries = $this->normalize_whitelist_lines($existing_lines);

        // 判定：本次請求推導出的 origin → 逐個併入
        foreach ((array)$origin_variants as $origin_variant) {
            $whitelist_entries[(string)$origin_variant] = true;
        }

        // 判定：bridge 埠每次啟動會變 → 永遠寫入本機萬用（bridge 端 origin_is_allowed 支援 :*）
        $whitelist_entries["http://127.0.0.1:*"]  = true;
        $whitelist_entries["https://127.0.0.1:*"] = true;

        // 判定：條目過多 → 只保留最後 50 條，避免無限增長
        if (count($whitelist_entries) > 50) {
            $whitelist_entries = array_slice($whitelist_entries, -50, 50, true);
        }

        return $whitelist_entries;
    }

    // 把檔案行整理成 set（去空白、去註解）
    private function normalize_whitelist_lines($raw_lines)
    {
        $whitelist_entries = [];

        // 判定：讀唔到（false）→ 回空集合
        if (is_array($raw_lines) === false) {
            return $whitelist_entries;
        }

        foreach ($raw_lines as $raw_line) {
            $whitelist_line = trim((string)$raw_line);

            // 判定：空白行 → 跳過
            if ($whitelist_line === "") {
                continue;
            }

            // 判定：註解行 → 跳過
            if (strpos($whitelist_line, "#") === 0) {
                continue;
            }

            $whitelist_entries[$whitelist_line] = true;
        }

        return $whitelist_entries;
    }
    private function build_origin_variants($origin)
    {
        $origin_parts = parse_url($origin);

        // 判定：解析失敗／冇 host → 空
        if ($origin_parts === false || !isset($origin_parts["host"])) {
            return [];
        }

        $origin_host      = $origin_parts["host"];
        $origin_port_text = "";

        // 判定：有 port → 帶落變體
        if (isset($origin_parts["port"])) {
            $origin_port_text = ":" . $origin_parts["port"];
        }

        // 判定：IP／localhost／單段名／IPv6 → 只出兩條（同 host）
        if ($this->is_bare_host($origin_host)) {
            return $this->build_scheme_variants($origin_host, $origin_port_text);
        }

        $origin_variants = $this->build_scheme_variants($origin_host, $origin_port_text);

        // 判定：來源係 www 開頭 → 補 apex；否則補 www
        if (strpos($origin_host, "www.") === 0) {
            $apex_host       = substr($origin_host, 4);
            $apex_variants   = $this->build_scheme_variants($apex_host, $origin_port_text);
            $origin_variants = array_merge($origin_variants, $apex_variants);
        } else {
            $www_variants    = $this->build_scheme_variants("www." . $origin_host, $origin_port_text);
            $origin_variants = array_merge($origin_variants, $www_variants);
        }

        return array_values(array_unique($origin_variants));
    }

    // 判定：裸 host（IP／localhost／單段名／IPv6）——唔加 www
    private function is_bare_host($origin_host)
    {
        // 判定：IPv4／IPv6 → 裸
        if (filter_var($origin_host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        // 判定：localhost → 裸
        if ($origin_host === "localhost") {
            return true;
        }

        // 判定：冇點（單段名）或含冒號（IPv6 無括號）→ 裸
        if (strpos($origin_host, ".") === false) {
            return true;
        }

        return strpos($origin_host, ":") !== false;
    }

    // 砌 scheme 變體（同一 host：http＋https）
    private function build_scheme_variants($origin_host, $origin_port_text)
    {
        return ["http://" . $origin_host . $origin_port_text, "https://" . $origin_host . $origin_port_text];
    }
}
