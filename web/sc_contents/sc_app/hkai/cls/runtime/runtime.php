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
        // Python 解析：.env（相對站根）→ 產品自帶 execute/python → engine（dev／build）→ 系統 where python
        $python_executable   = "";
        $python_env_setting  = sc_get_env("python_executable", "");
        $repo_root_text      = sc_normalize_path(sc_get_config("repo_root"));

        // 判定：.env 有設定 → 相對路徑以站根為基準
        if ($python_env_setting !== "") {
            $python_env_path = $python_env_setting;

            // 判定：相對路徑（唔係 C:/ 或 / 開頭）→ 接上站根
            if (preg_match('/^[A-Za-z]:[\/\/]|^\//', $python_env_path) !== 1) {
                $python_env_path = $repo_root_text . "/" . $python_env_path;
            }

            $python_env_path = sc_normalize_path($python_env_path);

            // 判定：檔案存在 → 採用
            if (file_exists($python_env_path) === true) {
                $python_executable = $python_env_path;
            }
        }

        // 判定：仍未找到 → 產品候選（execute/python → engine）
        if ($python_executable === "") {
            $python_candidate_list   = [];
            $python_candidate_list[] = dirname(sc_get_config("services")) . "/execute/python313/python.exe";
        $python_candidate_list[] = dirname(sc_get_config("services")) . "/execute/python/python.exe";
            $python_candidate_list[] = dirname(sc_get_config("services"), 2) . "/../engine/python313/python.exe";
            $python_candidate_list[] = dirname(sc_get_config("services"), 3) . "/../engine/python313/python.exe";

            // 逐個候選檢查
            foreach ($python_candidate_list as $python_candidate_item) {
                $python_candidate_normalized = sc_normalize_path($python_candidate_item);

                // 判定：檔案存在 → 採用
                if (file_exists($python_candidate_normalized) === true) {
                    $python_executable = $python_candidate_normalized;
                    break;
                }
            }
        }

        // 判定：仍未找到 → 用系統 where python
        if ($python_executable === "") {
            $where_output_lines = [];
            $where_exit_code    = 0;
            @exec("where python 2>nul", $where_output_lines, $where_exit_code);

            // 判定：有輸出且第一行係檔案 → 採用
            if (count($where_output_lines) > 0) {
                $where_python_path = trim($where_output_lines[0]);

                // 判定：檔案存在 → 採用
                if ($where_python_path !== "" && file_exists($where_python_path) === true) {
                    $python_executable = sc_normalize_path($where_python_path);
                }
            }
        }

        $storage_dir       = sc_get_data_dir();

        // ① python 找不到（.env／execute python／engine python／系統 where 全部失敗）
        if ($python_executable === "") {
            hkai()->runtime()->logger()->error("runtime check: no python found");
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
        sc_write_json($this->get_runtime_config_file(), $runtime_configs);
    }

    // runtime.json 是否與最新 runtime configs 不同（檔案不存在 / checksum 不符 → true）
    public function is_runtime_config_changed()
    {
        $runtime_configs = $this->get_runtime_configs();

        $runtime_configs_json     = sc_pretty_json($runtime_configs);
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
