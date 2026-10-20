<?php

namespace sc\app\hkai\cls;

// == hkai_core_Class：hkai app 核心（singleton——由 bridging hkai__core() 取得） ==
// == initial()：由 hkai_main_Class::initial() 呼叫——注入 app/storage 目錄 ==
// == 職責：路徑／設定 SSOT、runtime.json 讀寫、daemon 生命週期（冷啟動／心跳／檢查） ==

class hkai_core_Class
{
    // 熱 daemon 設定（PHP 唯一來源——每次查詢寫出 runtime.json，daemon 每輪重讀動態生效）
    // model 單一來源 = __init__.php initial_configs 的 "model"（[provider, model_name]）——model_name 組裝為 provider/model_name
    // 格式：openrouter/ 前綴 = 經 OpenRouter 路由（OPENROUTER_API_KEY 由 oauth-keys.env 注入）
    // 改法:改 __init__.php 的 "model" 設定 → 下次 Send 自動生效（msg_conf 帶入，daemon 依 model 重建 Coder）
    
    private static $instance = null;
    
    // hkai storage 目錄（sc_storage/hkai——initial 時從 controller 取得）
    private $storage_dir = null;
    
    private $hkai_configs = [];
    
    // 路徑設定（initial_configs 注入——路徑由入口提供，class 只收值；key 同名）
    private $hkai_config_file   = null;
    private $fsm_map_file        = null;
    private $about_file           = null;
    private $version_file         = null;
    private $sample_question_file = null;
    private $runtime_config_file = null;
    private $python_script_dir   = null;
    
    // 固定路徑（initial_configs 初始化時一次整理——依 storage_dir ＋ sc_config/hkai/hkai_config.json SSOT 推導）
    private $storage_dir_history    = null;
    private $storage_dir_archive    = null;
    private $storage_dir_messages   = null;
    private $storage_dir_bridge     = null;
    private $daemon_heartbeat_path  = null;
    private $process_list_path    = null;
    private $process_status_dir     = null;
    private $server_status_path     = null;
    private $settings_file           = null;
    
    // 外部環境依賴（機器層——env 檔路徑由入口 sc_get_config(app.env_file) 注入；私隱值讀自 .env）
    private $env_file          = null;
    private $python_executable = null;
    private $goose_executable  = null;
    private $aider_pylibs_dir  = null;

    // get_daemon_config 組裝結果快取（request 生命週期——同 request 內重複呼叫零成本）
    private $daemon_config_cache = null;
    
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
    
    // 初始化（由 hkai_main_Class::initial() 呼叫一次——注入 storage 目錄）
    public function initial($controller)
    {
        $this->storage_dir = $controller->get_storage_dir();
    }
    
    // 注入設定（__init__.php 入口呼叫——路徑與使用者設定集中於入口提供；必傳）
    // 參數 key：storage_dir（基礎目錄）、hkai_config_file（SSOT 完整路徑）、fsm_map_file（FSM 機器契約完整路徑）、about_file（關於 HKAI 資料源完整路徑）、version_file（版本來源完整路徑——VERSION 檔）、sample_question_file（範例問題題庫資料源完整路徑）、runtime_config_file（runtime.json 絕對位置）、python_script_dir（套件根）、env_file（機器層 env 檔完整路徑——sc_config/assets/app.php）
    // 結構性設定（filenames／folders／daemon 引擎組／server_settings）一律來自 hkai_config_file；機器層外部路徑（env 檔）由入口另傳
    public function initial_configs($daemon_setup)
    {
        // 依規則 49/50——key 同名取變數並集中宣告
        $storage_dir         = $daemon_setup["storage_dir"];
        $hkai_config_file   = $daemon_setup["hkai_config_file"];
        $fsm_map_file        = $daemon_setup["fsm_map_file"];
        $about_file          = $daemon_setup["about_file"];
        $version_file        = $daemon_setup["version_file"];
        $sample_question_file = $daemon_setup["sample_question_file"];
        $runtime_config_file = $daemon_setup["runtime_config_file"];
        $python_script_dir   = $daemon_setup["python_script_dir"];
        $env_file            = $daemon_setup["env_file"];

        $this->storage_dir         = $storage_dir;
        $this->hkai_config_file   = $hkai_config_file;
        $this->fsm_map_file        = $fsm_map_file;
        $this->about_file          = $about_file;
        $this->version_file        = $version_file;
        $this->sample_question_file = $sample_question_file;
        $this->runtime_config_file = $runtime_config_file;
        $this->python_script_dir   = $python_script_dir;
        $this->env_file            = $env_file;

        // 讀取靜態 SSOT（hkai_config.json）
        $this->load_hkai_config($hkai_config_file);
    }

    // 讀取 hkai_config.json（SSOT 靜態設定——失敗寫 runtime log、不中斷流程）
    private function load_hkai_config($hkai_config_file)
    {
        // 判定：檔案不存在 → 寫 log、中止
        if (!is_file($hkai_config_file)) {
            hkai__sc_runtime_io()->error("hkai_config not found: " . $hkai_config_file);
            return;
        }

        // 判定：JSON 解析失敗 → 寫 log、中止
        $hkai_config_data = json_decode(@file_get_contents($hkai_config_file), true);
        if (!is_array($hkai_config_data)) {
            hkai__sc_runtime_io()->error("hkai_config unreadable or invalid JSON: " . $hkai_config_file);
            return;
        }

        $this->hkai_configs = $hkai_config_data;

        // 逐項拆解（key 同名取變數）
        $filenames       = $hkai_config_data["filenames"] ?? [];
        $folders         = $hkai_config_data["folders"] ?? [];

        // 外部環境依賴（機器層——私隱值讀自 .env：python／goose 執行檔）
        $env_values = $this->read_env_values();
        $this->python_executable = $env_values["python_executable"] ?? "";
        $this->goose_executable  = $env_values["goose_executable"] ?? "";
        $this->aider_pylibs_dir   = $env_values["aider_pylibs_dir"] ?? "";

        // storage 子目錄（storage_dir ＋ SSOT folders）
        $this->storage_dir_history  = $this->storage_dir . "/" . ($folders["history"] ?? "history");
        $this->storage_dir_archive  = $this->storage_dir . "/" . ($folders["archive"] ?? "messages/archive");
        $this->storage_dir_messages = $this->storage_dir . "/" . ($folders["messages"] ?? "messages/polling");

        // 固定檔路徑（storage_dir ＋ SSOT filenames——heartbeat／process_list）
        $this->daemon_heartbeat_path = $this->storage_dir . "/" . ($filenames["heartbeat"] ?? "aider_daemon.heartbeat");
        $this->process_list_path   = $this->storage_dir . "/" . ($filenames["process_list"] ?? "process_status/process_list.jsonl");

        // 程序狀態目錄（storage_dir ＋ SSOT folders——每個程序每秒寫一份自己的心跳）
        $this->process_status_dir    = $this->storage_dir . "/" . ($folders["process_status"] ?? "process_status");

        // bridge 子目錄（storage_dir ＋ SSOT folders——啟動結果檔所在）
        $this->storage_dir_bridge = $this->storage_dir . "/" . ($folders["bridge"] ?? "bridge");

        // 啟動結果檔（bridge 開機自寫、PHP 讀——running／failed＋原因）
        $this->server_status_path = $this->storage_dir_bridge . "/" . ($filenames["server_status"] ?? "server_status.json");

        // 設定檔（sc_config/hkai/settings.json——同 hkai_config.json 同目錄；讀寫同一份）
        $this->settings_file = dirname($hkai_config_file) . "/" . ($filenames["settings"] ?? "settings.json");

    }

    // 讀取 .env 私隱值（機器層——parse_ini_file RAW；檔缺失／解析失敗 → 空表）
    private function read_env_values()
    {
        // 判定：env 檔未設定或唔存在 → 空表
        if (!is_file($this->env_file)) {
            return [];
        }

        $env_values = @parse_ini_file($this->env_file, false, INI_SCANNER_RAW);

        // 判定：解析失敗 → 空表
        if ($env_values === false) {
            return [];
        }

        return $env_values;
    }

    // 取得 hkai_configs（config_key＝頂層 key；null → 全部；key 存在 → 該項；key 不符 → 寫 log、回 null）
    public function get_hkai_configs($config_key = null)
    {
        // 判定：無參數 → 回傳全部
        if ($config_key === null) {
            return $this->hkai_configs;
        }

        // 判定：key 存在 → 回傳該項
        if (array_key_exists($config_key, $this->hkai_configs)) {
            return $this->hkai_configs[$config_key];
        }

        // key 不符 → 寫 log、回 null
        hkai__sc_runtime_io()->error("hkai_configs key not found: " . $config_key);
        return null;
    }

    // 引擎清單（manifest 頂層「*_daemon」設定組——資料驅動；加新引擎零改碼）
    public function get_daemon_engines()
    {
        $daemon_engines = [];
        $hkai_configs   = $this->hkai_configs;

        foreach ($hkai_configs as $hkai_config_key => $hkai_config_item) {
            // 判定：key 唔以 _daemon 結尾 → 唔係引擎設定組
            if (substr((string)$hkai_config_key, -7) !== "_daemon") {
                continue;
            }

            // 判定：值唔係陣列（設定組）→ 唔算
            if (!is_array($hkai_config_item)) {
                continue;
            }

            $daemon_engines[] = $hkai_config_key;
        }

        return $daemon_engines;
    }

    // 現行引擎（動態優先——runtime.json 已存值＞manifest 出廠值；皆唔合法 → aider_daemon——同 Python 端 read_daemon_engine 一致）
    public function get_daemon_engine()
    {
        $daemon_engines = $this->get_daemon_engines();

        // runtime.json 已存值（動態層——切換寫入處）
        $current_runtime_configs = json_decode(@file_get_contents($this->runtime_config_file), true) ?? [];
        $runtime_daemon_engine   = $current_runtime_configs["daemon_engine"] ?? "";

        // 判定：動態值合法 → 回傳
        if (in_array($runtime_daemon_engine, $daemon_engines)) {
            return $runtime_daemon_engine;
        }

        // manifest 出廠值（靜態層）
        $static_daemon_engine = $this->hkai_configs["daemon_engine"] ?? "";

        // 判定：靜態值合法 → 回傳
        if (in_array($static_daemon_engine, $daemon_engines)) {
            return $static_daemon_engine;
        }

        // 皆唔合法（缺／打錯）→ 預設
        return "aider_daemon";
    }

    // daemon 設定（bridging 取得入口——呼叫端一律經 hkai__core()；內部路徑由 storage 推導，零寫死）
    // PHP 自用（request／message／log cleanup）——Python 端讀的是 runtime.json ＋ msg_conf
    public function get_daemon_config()
    {
        // 快取（request 生命週期——同 request 內重複呼叫零成本）
        if (isset($this->daemon_config_cache)) {
            return $this->daemon_config_cache;
        }
        
        $daemon_config = [];
        $daemon_config["daemon_history_dir"]    = $this->storage_dir_history;
        $daemon_config["daemon_archive_dir"]    = $this->storage_dir_archive;
        $daemon_config["daemon_heartbeat_path"] = $this->daemon_heartbeat_path;
        $daemon_config["process_list_path"]   = $this->process_list_path;
        $daemon_config["process_status_dir"]    = $this->process_status_dir;
        $daemon_config["runtime_config_file"]   = $this->runtime_config_file;
        
        $this->daemon_config_cache = $daemon_config;
        return $daemon_config;
    }
    
    // runtime configs（Python 讀的唯一動態設定——靜態 SSOT 全內容為底，動態 key 覆蓋）
    // 覆蓋層：storage 基準路徑 ＋ 靜態 SSOT 路徑 ＋ 現 runtime.json 已存的 server_settings（含 testing_result）
    public function get_runtime_configs()
    {
        // 靜態 SSOT 全內容為底（filenames／folders／daemon 引擎組／server_settings）
        $hkai_configs = json_decode(@file_get_contents($this->hkai_config_file), true) ?? [];
        
        // 動態覆蓋：storage 基準路徑 ＋ 靜態 SSOT 路徑 ＋ aider_daemon messages 目錄（full path）
        $runtime_override = [];
        $runtime_override["storage_dir"]              = $this->storage_dir;
        $runtime_override["static_config_path"]       = $this->get_hkai_config_file();
        $runtime_override["daemon_messages_dir"] = $this->storage_dir_messages;

        // 機器層外部路徑（Python 讀 runtime.json 取——key 名沿用既有契約；值＝.env 私隱值）
        $runtime_override["external_paths"] = [
            "python_executable" => $this->python_executable,
            "oauth_keys_file"   => $this->env_file,
            "goose_executable"  => $this->goose_executable,
            "aider_pylibs_dir"  => $this->aider_pylibs_dir,
        ];

        $runtime_configs = array_replace($hkai_configs, $runtime_override);
        
        // server_settings 動態層（現 runtime.json 已存值優先——未存時沿用靜態預設）
        $current_runtime_configs = json_decode(@file_get_contents($this->runtime_config_file), true) ?? [];
        $current_server_settings = $current_runtime_configs["server_settings"] ?? [];
        $runtime_configs["server_settings"] = array_replace($runtime_configs["server_settings"] ?? [], $current_server_settings);

        // daemon_engine 動態層（現 runtime.json 已存合法值優先——切換選擇唔畀重生冚返；缺／唔合法沿用靜態出廠值）
        $current_daemon_engine = $current_runtime_configs["daemon_engine"] ?? "";
        $daemon_engines        = $this->get_daemon_engines();

        // 判定：動態值合法 → 覆蓋靜態出廠值
        if (in_array($current_daemon_engine, $daemon_engines)) {
            $runtime_configs["daemon_engine"] = $current_daemon_engine;
        }
        
        return $runtime_configs;
    }
    
    // python 執行檔路徑（機器層——.env 私隱值；daemon 拉起用）
    public function get_python_executable()
    {
        return $this->python_executable;
    }
    
    // python 套件根目錄（services——拉起 python 的穩定來源；同一概念不另推導）
    public function get_python_script_dir()
    {
        return $this->python_script_dir;
    }

    // env 檔（=.env）完整路徑（機器層——入口 sc_get_config(app.env_file) 注入；對外沿用 oauth_keys_file 名；daemon 讀檔同源）
    public function get_oauth_keys_file()
    {
        return $this->env_file;
    }

    // 靜態 SSOT 完整路徑（hkai_config_file）
    public function get_hkai_config_file()
    {
        return $this->hkai_config_file;
    }
    
    // FSM 機器契約完整路徑（fsm_map_file——狀態機讀取）
    public function get_fsm_map_file()
    {
        return $this->fsm_map_file;
    }

    // 關於 HKAI 資料源完整路徑（about_hkai.php——about 分頁唯一來源）
    public function get_about_file()
    {
        return $this->about_file;
    }

    // 版本來源完整路徑（repo 根 VERSION 檔——版本唯一來源）
    public function get_version_file()
    {
        return $this->version_file;
    }
    
    // 範例問題題庫資料源完整路徑（sample_question.php——inspiration 按鈕唯一來源）
    public function get_sample_question_file()
    {
        return $this->sample_question_file;
    }
    
    // runtime.json 絕對位置（入口定義——python 唯一動態設定來源）
    public function get_runtime_config_file()
    {
        return $this->runtime_config_file;
    }
    
    // 程序清單檔（process_status/process_list.jsonl——Python spawn 端寫、health 清潔工剔死行、kill 血緣讀）
    public function get_process_list_path()
    {
        return $this->process_list_path;
    }
    
    // 程序心跳目錄（process_status——每程序一檔、每秒自我覆寫）
    public function get_process_status_dir()
    {
        return $this->process_status_dir;
    }

    // 啟動結果檔完整路徑（bridge 開機自寫、PHP 拉起後輪詢讀——storage_dir/bridge/server_status.json）
    public function get_server_status_path()
    {
        return $this->server_status_path;
    }
    
    // 寫入 runtime.json（Python 讀的唯一動態檔——設定儲存用）
    // 帶 config_key／config_value：先以 array_replace 併入該 key（如 server_settings）再寫
    public function write_runtime_configs($config_key = "", $config_value = [])
    {
        $runtime_configs = $this->get_runtime_configs();
        
        // 判定：有帶更新（config_key 非空）→ array_replace 併入該 key
        if ($config_key !== "") {
            $runtime_configs[$config_key] = array_replace($runtime_configs[$config_key] ?? [], $config_value);
        }
        
        // 判定：未帶更新且內容未變 → 不寫（checksum 比對——sc_runtime）
        if ($config_key === "") {
            if (!hkai__sc_runtime()->is_runtime_config_changed()) {
                return;
            }
        }
        
        $this->save_runtime_configs($runtime_configs);
    }

    // 現行引擎寫入（agent 選擇——存 runtime.json 動態層；manifest 留出廠值）
    public function write_daemon_engine($daemon_engine)
    {
        $runtime_configs = $this->get_runtime_configs();
        $runtime_configs["daemon_engine"] = $daemon_engine;

        $this->save_runtime_configs($runtime_configs);
    }

    // runtime.json 落地（唯一寫檔尾——write_runtime_configs／write_daemon_engine 共用）
    private function save_runtime_configs($runtime_configs)
    {
        $runtime_configs_json = json_encode($runtime_configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        file_put_contents($this->runtime_config_file, $runtime_configs_json, LOCK_EX);
    }

    // 設定讀取（sc_config/hkai/settings.json——單一設定檔；缺檔／壞 → 空表）
    public function read_effective_settings()
    {
        $settings = json_decode(@file_get_contents($this->settings_file), true);

        // 判定：非陣列（缺檔／壞檔）→ 空表
        if (!is_array($settings)) {
            $settings = [];
        }

        return $settings;
    }

    // 設定寫入（sc_config/hkai/settings.json——指定組 read-modify-write；同組同名 key 覆蓋）
    public function write_user_settings($settings_group_key, $settings_group_values)
    {
        // 現有設定為底（保住未更新嘅組與 key——read-modify-write）
        $settings = [];
        $current_settings = json_decode(@file_get_contents($this->settings_file), true);

        // 判定：現有檔可讀（陣列）→ 以佢為底
        if (is_array($current_settings)) {
            $settings = $current_settings;
        }

        // 目標組現值（保住同組未更新嘅 key）
        $current_settings_group = $settings[$settings_group_key] ?? [];

        // 判定：現組非陣列（壞檔／首寫）→ 空組起手
        if (!is_array($current_settings_group)) {
            $current_settings_group = [];
        }

        // 併入更新（同組同名 key 覆蓋）
        $settings[$settings_group_key] = array_replace($current_settings_group, $settings_group_values);

        $settings_json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 檔尾補換行（同現有檔格式）
        file_put_contents($this->settings_file, $settings_json . "\n", LOCK_EX);
    }
    
    // 單一 PID 存活檢查（Windows：tasklist 唯讀查詢；Linux：/proc；其他：ps——找不到回 false）
    public function is_process_alive($process_pid)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → false
        if ($process_pid < 1) {
            return false;
        }

        // Linux：/proc 原生檔案系統（零子進程）
        if (PHP_OS_FAMILY === "Linux") {
            return is_dir("/proc/" . $process_pid);
        }

        // Windows：tasklist 唯讀查詢（找不到 → 只有一行 INFO 提示）
        if (PHP_OS_FAMILY === "Windows") {
            $tasklist_lines = [];
            exec('tasklist /FI "PID eq ' . $process_pid . '" /FO CSV /NH 2>nul', $tasklist_lines);

            $found_task = trim($tasklist_lines[0] ?? "");

            // 判定：無輸出／INFO 提示 → 不存在
            if ($found_task === "" || strpos($found_task, "INFO:") !== false) {
                return false;
            }

            $task_data = str_getcsv($found_task, ",", '"', "\\");

            return isset($task_data[1]) && (int)$task_data[1] === $process_pid;
        }

        // macOS／其他：ps 單一 pid 查詢
        $ps_lines = [];
        exec("ps -o pid= -p " . $process_pid . " 2>/dev/null", $ps_lines);

        return (int)trim($ps_lines[0] ?? "0") === $process_pid;
    }

    // 全程序表（單次取回——Windows：PowerShell Win32_Process → JSON；POSIX：ps 全表）
    // 回傳 [pid => {pid, ppid, name, command_line}]；取不到 → 空陣列
    public function get_process_table()
    {
        $process_table = [];

        if (PHP_OS_FAMILY === "Windows") {
            // wmic 全表（0.3s——比逐關鍵詞過濾快 30 倍；欄位依字母序：Node, CommandLine, Name, ParentProcessId, ProcessId）
            $wmic_lines = [];
            exec("wmic process get ProcessId,ParentProcessId,Name,CommandLine /format:csv 2>nul", $wmic_lines);

            foreach ($wmic_lines as $wmic_line) {
                $wmic_line = trim($wmic_line);

                // 判定：空行 → 跳過
                if ($wmic_line === "") {
                    continue;
                }

                // 註：wmic 的 CSV 不轉義內含逗號的 CommandLine → 從「右邊」取欄位
                //     （尾三欄固定是 Name, ParentProcessId, ProcessId——Name 不含逗號）
                $fields = explode(",", $wmic_line);

                // 判定：欄位不足（表頭／雜訊列）→ 跳過
                if (count($fields) < 5) {
                    continue;
                }

                $process_pid  = (int)$fields[count($fields) - 1];
                $process_ppid = (int)$fields[count($fields) - 2];
                $process_name = trim($fields[count($fields) - 3]);

                // 判定：pid 無效（表頭列）→ 跳過
                if ($process_pid < 1) {
                    continue;
                }

                // 命令列＝剩下的中間欄（逗號原樣接回）
                $command_line = implode(",", array_slice($fields, 1, count($fields) - 4));

                $process_table[$process_pid] = [
                    "pid"          => $process_pid,
                    "ppid"         => $process_ppid,
                    "name"         => $process_name,
                    "command_line" => trim($command_line),
                ];
            }

            return $process_table;
        }

        // POSIX：ps 全表（pid ppid comm args）
        $ps_lines = [];
        exec("ps -eo pid=,ppid=,comm=,args= 2>/dev/null", $ps_lines);

        foreach ($ps_lines as $ps_line) {
            $ps_parts = preg_split('/\s+/', trim($ps_line), 4);

            $process_pid = (int)($ps_parts[0] ?? 0);

            // 判定：pid 無效 → 跳過
            if ($process_pid < 1) {
                continue;
            }

            $process_table[$process_pid] = [
                "pid"          => $process_pid,
                "ppid"         => (int)($ps_parts[1] ?? 0),
                "name"         => (string)($ps_parts[2] ?? ""),
                "command_line" => (string)($ps_parts[3] ?? ""),
            ];
        }

        return $process_table;
    }

    // 掃描本系統程序（① 關鍵詞命中 cmdline ∪ ② 已知根 pid 的血緣子孫——找出「無落紙」的程序）
    // $scan_keywords：可辨識的 cmdline 關鍵詞（bridge.main／aider_daemon.main／goose_daemon.main）
    // $root_pids：已知根 pid（狀態檔裡的 pid——往下展開子孫；multiprocessing 子程序無可辨識 cmdline，只能靠 ppid）
    // $name_filter：映像檔名必須包含的字串（本系統全為 Python 程序——濾掉血緣上的 console host 等系統雜訊；空＝不濾）
    // 回傳 [{pid, name, command_line}]（排除掃描自身——PHP／wmic 這條請求鏈）
    public function scan_processes($scan_keywords, $root_pids = [], $name_filter = "python")
    {
        $process_table = $this->get_process_table();

        // 判定：取不到程序表 → 空
        if (count($process_table) === 0) {
            return [];
        }

        $matched_pids = [];

        // ① 關鍵詞命中（cmdline 比對——不分大小寫）
        foreach ($process_table as $table_pid => $table_row) {
            foreach ((array)$scan_keywords as $scan_keyword) {
                // 判定：關鍵詞為空 → 不比對
                if ((string)$scan_keyword === "") {
                    continue;
                }

                if (stripos($table_row["command_line"], (string)$scan_keyword) !== false) {
                    $matched_pids[$table_pid] = true;
                    break;
                }
            }
        }

        // ② 血緣展開（命中者 ＋ 已知根 pid 皆為種子；逐層往下找子孫——上限 5 層）
        $seed_pids = array_values(array_unique(array_merge(array_keys($matched_pids), array_map("intval", (array)$root_pids))));

        // 我方直譯器（由種子自身的命令列推導——不寫死路徑；本系統四層共用同一個 python）
        $interpreter_paths = [];
        foreach ($seed_pids as $seed_pid) {
            $seed_row = $process_table[$seed_pid] ?? null;

            // 判定：種子不在程序表（已被收掉）→ 跳過
            if ($seed_row === null) {
                continue;
            }

            $interpreter_path = $this->extract_interpreter_path($seed_row["command_line"]);

            // 判定：取不到 → 跳過
            if ($interpreter_path !== "") {
                $interpreter_paths[strtolower($interpreter_path)] = true;
            }
        }

        for ($depth = 0; $depth < 5; $depth++) {
            $found_child = false;

            foreach ($process_table as $table_pid => $table_row) {
                // 判定：父程序不在種子裡 → 不是子孫
                if (!in_array($table_row["ppid"], $seed_pids, true)) {
                    continue;
                }

                // 判定：已在清單 → 跳過（避免無限迴圈）
                if (isset($matched_pids[$table_pid])) {
                    continue;
                }

                $matched_pids[$table_pid] = true;
                $seed_pids[]              = $table_pid;
                $found_child              = true;
            }

            // 判定：這層沒找到新子孫 → 提早結束
            if (!$found_child) {
                break;
            }
        }

        // 種子自身也要列（狀態檔登記的 pid——即使 cmdline 認不出，也是本系統程序）
        foreach ($seed_pids as $seed_pid) {
            // 判定：pid 有效且在程序表 → 列入
            if ($seed_pid > 0 && isset($process_table[$seed_pid])) {
                $matched_pids[$seed_pid] = true;
            }
        }

        // 掃描自身（PHP 這條請求鏈：pwsh／powershell 帶著我們的查詢字串）→ 排除
        $self_pid = getmypid();
        $scan_processes = [];

        foreach (array_keys($matched_pids) as $matched_pid) {
            $table_row = $process_table[$matched_pid] ?? null;

            // 判定：pid 不存在／是自己／是掃描工具 → 跳過
            if ($table_row === null || (int)$matched_pid === (int)$self_pid) {
                continue;
            }

            if (stripos($table_row["command_line"], "Get-CimInstance") !== false || stripos($table_row["command_line"], "wmic") !== false) {
                continue;
            }

            // 判定：映像檔名不符（如血緣上的 conhost.exe 等系統程序）→ 跳過
            if ((string)$name_filter !== "" && stripos($table_row["name"], (string)$name_filter) === false) {
                continue;
            }

            // 判定：直譯器不是我方那顆（如 Hermes 自帶 venv 的 python——同機不同系統）→ 跳過
            if (count($interpreter_paths) > 0) {
                $row_interpreter = strtolower($this->extract_interpreter_path($table_row["command_line"]));

                if (!isset($interpreter_paths[$row_interpreter])) {
                    continue;
                }
            }

            $scan_processes[] = [
                "pid"          => (int)$table_row["pid"],
                "name"         => (string)$table_row["name"],
                "command_line" => (string)$table_row["command_line"],
            ];
        }

        // 排序：pid 小 → 大
        usort($scan_processes, function ($process_a, $process_b) {
            return $process_a["pid"] <=> $process_b["pid"];
        });

        return $scan_processes;
    }

    // 命令列 → 直譯器路徑（第一個 token；支援開頭帶引號的路徑）
    private function extract_interpreter_path($command_line)
    {
        $command_line = trim((string)$command_line);

        // 判定：空 → 空
        if ($command_line === "") {
            return "";
        }

        // 開頭帶引號（路徑含空白者）→ 取到收尾引號
        if (substr($command_line, 0, 1) === "\"") {
            $end_pos = strpos($command_line, "\"", 1);

            return $end_pos === false ? "" : substr($command_line, 1, $end_pos - 1);
        }

        // 否則取第一個空白前的字（不含參數）
        $space_pos = strpos($command_line, " ");

        return $space_pos === false ? $command_line : substr($command_line, 0, $space_pos);
    }
    
}
