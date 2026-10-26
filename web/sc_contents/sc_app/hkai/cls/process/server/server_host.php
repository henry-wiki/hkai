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

    // 附加本次請求來源去 origin 白名單（補齊 http/https × apex/www；去重·LOCK_EX）
    public function append_origin_whitelist()
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

        $existing_origins = [];

        // 判定：白名單已存在 → 讀現有條目
        if (is_file($whitelist_file)) {
            foreach (file($whitelist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $whitelist_line) {
                $existing_origins[] = trim((string)$whitelist_line);
            }
        }

        $new_origin_lines = [];

        // 逐條 variant（已有＝跳；否則排隊寫）
        foreach ($origin_variants as $origin_variant) {
            // 判定：已存在 → 跳（去重）
            if (in_array($origin_variant, $existing_origins, true)) {
                continue;
            }

            $new_origin_lines[] = $origin_variant;
        }

        // 判定：冇新條目 → 唔使寫
        if (count($new_origin_lines) === 0) {
            return;
        }

        @file_put_contents($whitelist_file, implode("\n", $new_origin_lines) . "\n", FILE_APPEND | LOCK_EX);
    }

    // 砌來源變體（domain → http/https × apex/www 四條；IP／localhost／單段名／IPv6 → http/https 兩條）
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
