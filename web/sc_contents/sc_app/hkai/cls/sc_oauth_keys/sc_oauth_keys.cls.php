<?php

namespace sc\app\hkai\cls\sc_oauth_keys;

// == sc_oauth_keys_Class：oauth keys（.env——私隱檔）存取（singleton——經 bridging hkai__sc_oauth_keys() 取得） ==
// == 職責：read_keys（KEY→value）／get_key／write_key（單鍵原子寫——temp+rename）／make_mask（顯示遮蔽） ==
// == 檔契約：<NAME>_API_KEY 行（值可 "..." 引號式）；另可放其他私隱設定行（原樣讀取）；解析＝parse_ini_file（RAW——值原樣）；寫入＝統一引號＋跳脫；EOL 跟原檔 ==

class sc_oauth_keys_Class
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

    // env 檔路徑（hkai_core 機器層——入口 sc_get_config(app.env_file) 注入；daemon 同源）
    private function get_env_file_path()
    {
        return hkai__core()->get_oauth_keys_file();
    }

    // 讀全部 key（平台 INI 解析器——parse_ini_file RAW；檔缺失／解析失敗 → 空表）
    public function read_keys()
    {
        $env_file = $this->get_env_file_path();

        // 判定：檔唔存在 → 空表
        if (!is_file($env_file)) {
            return [];
        }

        // 解析（INI_SCANNER_RAW——值原樣：唔轉義、唔轉型、唔插值；外層引號由解析器剝）
        $keys = @parse_ini_file($env_file, false, INI_SCANNER_RAW);

        // 判定：解析失敗 → 空表
        if ($keys === false) {
            return [];
        }

        return $keys;
    }

    // 值正規化（剝外層配對引號——比照 dotenv 讀法；值可能係 "..." 引號式）
    public function normalize_value($raw_value)
    {
        $value = trim($raw_value);

        // 判定：短過 2 字 → 無引號可剝
        if (strlen($value) < 2) {
            return $value;
        }

        $first_char = $value[0];
        $last_char  = substr($value, -1);

        // 判定：首字唔係引號 → 原樣
        if (!in_array($first_char, ['"', "'"])) {
            return $value;
        }

        // 判定：首尾唔同款引號 → 原樣（唔配對唔剝）
        if ($first_char !== $last_char) {
            return $value;
        }

        return substr($value, 1, -1);
    }

    // 單鍵讀取（唔存在 → ""）
    public function get_key($name)
    {
        $keys = $this->read_keys();

        return $keys[$name] ?? "";
    }

    // 顯示遮蔽（18 星＋尾 4 字；空值回 ""）
    public function make_mask($value)
    {
        // 判定：空值 → 無 mask
        if ($value === "") {
            return "";
        }

        // 判定：短過 4 字 → 全星
        if (strlen($value) < 4) {
            return str_repeat("*", 18);
        }

        return str_repeat("*", 18) . substr($value, -4);
    }

    // 單鍵寫入（改現行／無則檔末補；temp+rename 原子；缺檔建立）
    public function write_key($name, $value)
    {
        $env_file = $this->get_env_file_path();
        $eol      = "\r\n";
        $raw      = "";

        // 判定：檔存在 → 讀原檔（EOL 跟原文）
        if (is_file($env_file)) {
            $raw = (string)@file_get_contents($env_file);
            if (strpos($raw, "\r\n") === false) {
                $eol = "\n";
            }
        }

        // 判定：空（新檔）→ 建立帶頭
        if ($raw === "") {
            $raw = "# API KEYS" . $eol;
        }

        // 值統一引號式（比照檔內慣例 "..."；內層跳脫 \ 與 "——dotenv 讀法對稱）
        $escaped_value = str_replace(["\\", "\""], ["\\\\", "\\\""], $value);
        $quoted_value  = "\"" . $escaped_value . "\"";

        // 改現行（只換值部位——行結構原樣）
        $pattern  = "/^(" . preg_quote($name, "/") . "[ \t]*=)[^\r\n]*/m";
        $replaced = preg_replace_callback($pattern, function ($matched) use ($quoted_value) {
            return $matched[1] . $quoted_value;
        }, $raw, 1, $replace_count);

        // 判定：無現行 → 檔末補一行
        if ($replace_count === 0) {
            $replaced = rtrim($raw, "\r\n") . $eol . $name . "=" . $quoted_value . $eol;
        }

        // 原子寫（同目錄 temp + rename）
        $temp_file = $env_file . ".tmp." . uniqid();
        file_put_contents($temp_file, $replaced, LOCK_EX);
        rename($temp_file, $env_file);
    }

}
