<?php

namespace sc\app\hkai\cls\runtime;

// == runtime_Class：hkai app 層 runtime 中樞（singleton——經 bridging hkai()->runtime() 取得） ==
// == 職責：runtime 設定讀寫（get／write_runtime_configs、read／save_runtime_configs）；agent 選擇已搬 agent
// == 上游：PHP request（server settings／bridge 拉起）、runtime check、agent（runtime I/O）
// == 下游：hkai()->get_hkai_configs()（manifest 靜態層）、sc_config／sc_get_env（路徑與私隱值）、runtime.json

class runtime_Class
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

    /**
     * logger 域（log 寫入——info／warn／error）
     *
     * @return \sc\app\hkai\cls\runtime\logger\logger_Class
     */
    public function logger()
    {
        return logger\logger_Class::getInstance();
    }

    // runtime 設定檢查（必要設定缺失／不可用 → 寫 runtime log；不中斷流程）
    public function check()
    {
        $python_executable = sc_get_env("python_executable", "");
        $storage_dir       = sc_get_config("storage.app_dir");

        // ① python_executable 未設定
        if ($python_executable === "") {
            hkai()->runtime()->logger()->error("runtime check: python_executable not set");
        }

        // ① python_executable 已設定 → 檢查檔案存在
        if ($python_executable !== "") {
            if (!file_exists($python_executable)) {
                hkai()->runtime()->logger()->error("runtime check: python_executable not found: " . $python_executable);
            }
        }

        // ② storage 目錄不可寫
        if (!is_writable($storage_dir)) {
            hkai()->runtime()->logger()->error("runtime check: storage dir not writable: " . $storage_dir);
        }
    }

    // runtime.json 絕對位置（sc_config("hkai.runtime_config_file")——python 唯一動態設定來源）
    public function get_runtime_config_file()
    {
        return sc_get_config("hkai.runtime_config_file");
    }

    // 讀 runtime.json（缺檔／壞檔 → 空表；私有——只供本 class 合成）
    public function read_runtime_configs()
    {
        $runtime_configs = json_decode(@file_get_contents($this->get_runtime_config_file()), true);

        // 判定：非陣列（缺檔／壞檔）→ 空表
        if (!is_array($runtime_configs)) {
            return [];
        }

        return $runtime_configs;
    }

    // runtime configs（Python 讀的唯一動態設定——manifest 全內容為底，動態層覆蓋）
    public function get_runtime_configs()
    {
        $runtime_configs = hkai()->get_hkai_configs();

        // 動態層：server_settings（現 runtime.json 已存值優先——未存時沿用 manifest 預設）
        $saved_runtime_configs = $this->read_runtime_configs();
        $saved_server_settings = $saved_runtime_configs["server_settings"] ?? [];

        $runtime_configs["server_settings"] = array_replace($runtime_configs["server_settings"] ?? [], $saved_server_settings);

        // 動態層：agent（切換結果——agent 名）＋ daemon_engine（對應 daemon 名——Python 過渡期相容）
        $runtime_configs["agent"]         = hkai()->agent()->get_agent();
        $runtime_configs["daemon_engine"] = hkai()->agent()->get_agent_daemon($runtime_configs["agent"]);

        return $runtime_configs;
    }

    // 寫入 runtime.json（指定組 read-modify-write；未帶更新且內容未變 → 不寫）
    public function write_runtime_configs($config_key = "", $config_value = [])
    {
        $runtime_configs = $this->get_runtime_configs();

        // 判定：有帶更新 → array_replace 併入該組
        if ($config_key !== "") {
            $runtime_configs[$config_key] = array_replace($runtime_configs[$config_key] ?? [], $config_value);
        }

        // 判定：未帶更新且內容未變 → 不寫（checksum 比對）
        if ($config_key === "") {
            if (!$this->is_runtime_config_changed()) {
                return;
            }
        }

        $this->save_runtime_configs($runtime_configs);
    }

    // runtime.json 落地（唯一寫檔尾）
    public function save_runtime_configs($runtime_configs)
    {
        $runtime_configs_json = json_encode($runtime_configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        file_put_contents($this->get_runtime_config_file(), $runtime_configs_json, LOCK_EX);
    }

    // runtime.json 是否與最新 runtime configs 不同（檔案不存在 / checksum 不符 → true）
    public function is_runtime_config_changed()
    {
        $runtime_configs = $this->get_runtime_configs();

        $runtime_configs_json     = json_encode($runtime_configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $runtime_configs_checksum = md5($runtime_configs_json);

        $runtime_config_path = $this->get_runtime_config_file();

        // 判定：檔案不存在 → 視為已變更
        if (!@file_exists($runtime_config_path)) {
            return true;
        }

        // 判定：checksum 不符 → 已變更
        if (md5_file($runtime_config_path) !== $runtime_configs_checksum) {
            return true;
        }

        return false;
    }
}
