<?php

namespace sc\setup\migrate;

// == migrate_env：env 檔載入器（由 sc_env 整份複製——唯一差別＝檔案路徑由呼叫端指定）
// == 位置：web/setup/cls/migrate/（搬遷域）
// == load()：__construct 時讀指定 .env（parse_ini_file RAW——與 sc_env 同一讀法）
// == get()：平面鍵讀取；get_all()：全表
// == 上游：get_migrate_env()（_.func.php 橋接）、staging（Settings 預填）

class migrate_env_Class
{
    private $env_values = array();
    private $loaded     = false;
    private $env_file   = "";

    // 建構（指定 .env 路徑；載入一次）
    public function __construct($env_file = "")
    {
        $this->env_file = $env_file;

        $this->load();
    }

    // 載入 .env 全部值（指定路徑；只載入一次）
    public function load()
    {
        // 判定：已載入 → 跳過
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        // 判定：路徑空／檔唔存在 → 空
        if ($this->env_file === "" || is_file($this->env_file) !== true) {
            return;
        }

        $env_values = parse_ini_file($this->env_file, false, INI_SCANNER_RAW);

        // 判定：解析失敗（非陣列）→ 空
        if (!is_array($env_values)) {
            return;
        }

        $this->env_values = $env_values;
    }

    // 讀取（平面鍵；無值回 default）
    public function get($key, $default = null)
    {
        // 判定：key 不存在 → 回 default
        if (!isset($this->env_values[$key])) {
            return $default;
        }

        return $this->env_values[$key];
    }

    // 讀取全部（平面 map 原形）
    public function get_all()
    {
        return $this->env_values;
    }
}
