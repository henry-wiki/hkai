<?php

namespace sc\app\hkai\cls\process;

// == process_Class：process 域根（singleton——經 hkai()->process() 取得） ==
// == 子域：bridge()（通訊 client）／bridge_log()（日誌）／bridge_watch()（心跳狀態／監視）／server()（生命週期＋server_port 埠工具）；本體＝本系統程序查詢 ==
// == 職責：向 Python bridge 要「本系統程序即時清單」（/process/scan/）——存活判定與終止白名單的唯一來源
// ==       心跳純讀（read_bridge_heartbeat）＋交 bridge 殺程序（kill_process——PHP 唔直接落刀）＋殺戳來源（build_kill_source_text）
// ==       心跳檔（process_status/*.json——Python 每程序每秒寫）讀取（kill 白名單認名）；程序清單（process_status/process_list.jsonl）血緣殺序（剔死行已交 Python 各程序） ==
// == program 定義：spawn 目標的點分路徑（對應 process_list 的 program 欄、process_status/*.json 第 3 欄） ==
// == 呈現（清單／計數／KILL 鈕）屬 sc_ai_settings 的 process 分頁；本檔只負責「問 Python」 ==

class process_Class
{
    // 心跳停滯上限（秒——pid 還在但心跳停這麼久：pid 被重用或心跳執行緒卡死，仍視為死）
    const STATUS_STALE_SEC = 60;

    // 程序層級顯示順序（誰生誰的鏈序——計數與清單共用）
    const PROCESS_RANK = ["bridge" => 1, "goose_daemon" => 2, "worker" => 3, "goose" => 4];

    // 現行agent鏈顯示名（daemon_engine 值 → [daemon 名, agent名]——計數摘要用）
    const ENGINE_CHAINS = ["goose_daemon" => ["goose_daemon", "goose"]];
    
    private static $instance = null;

    // program 清單（bridge 精篩用——goose_daemon 由 -m 起，worker／agent（goose）由血緣認）
    private $program_names = [];

    // python 程序記錄快照（一次 wmic 掃描結果——同一請求內快取重用；呼叫端自行過濾）
    private $python_process_records_cache = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    // 初始化（program 清單單一來源——對應 process_list 的 program 欄）
    public function __construct()
    {
        $this->program_names = [
            "bridge.main",
            "goose_daemon.main",
            "goose_daemon.worker",
            "goose_entry.run_coder",
        ];
    }


    /**
     * launcher 子域（exec python launcher——拉起 daemon）
     *
     * @return \sc\app\hkai\cls\process\launcher\launcher_Class
     */
    public function launcher()
    {
        return launcher\launcher_Class::getInstance();
    }

    /**
     * bridge 通訊子域（client——request／notify）
     *
     * @return \sc\app\hkai\cls\process\bridge\bridge_Class
     */
    public function bridge()
    {
        return bridge\bridge_Class::getInstance();
    }

    /**
     * bridge 日誌子域（audit log 讀取）
     *
     * @return \sc\app\hkai\cls\process\bridge\bridge_log_Class
     */
    public function bridge_log()
    {
        return bridge\bridge_log_Class::getInstance();
    }

    /**
     * bridge 心跳觀測域（狀態查詢＋等心跳檔消失）
     *
     * @return \sc\app\hkai\cls\process\bridge\bridge_watch_Class
     */
    public function bridge_watch()
    {
        return bridge\bridge_watch_Class::getInstance();
    }

    /**
     * python server 生命週期域（起／停／重啟；位址由 server_host 提供）
     *
     * @return \sc\app\hkai\cls\process\server\server_Class
     */
    public function server()
    {
        return server\server_Class::getInstance();
    }

    /**
     * 指示檔讀寫域（system_prompt／system_env 路徑、讀、寫、出廠覆寫）
     *
     * @return \sc\app\hkai\cls\process\instruction\instruction_Class
     */
    public function instruction()
    {
        return instruction\instruction_Class::getInstance();
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

    // 掃描本機程序（bridge → Python；帶 program 清單精篩——根看 cmdline、子看血緣）
    public function get_scanned_processes()
    {
        $scan_data = hkai()->process()->bridge()->request("/process/scan/", [
            "process_name_prefixes" => ["python"],
            "program_names"         => $this->program_names,
        ]);

        // 判定：bridge 失敗 → 空清單（不誤給終止權）
        if (($scan_data["ok"] ?? false) !== true) {
            return [];
        }

        return ($scan_data["processes"] ?? []);
    }

    // 由掃描列取存活 pid 集（pid → true；空＝bridge 失敗）
    public function collect_alive_pids($scanned_processes)
    {
        // 判定：無掃描結果 → null（呼叫端沿用舊查法）
        if (count($scanned_processes) === 0) {
            return null;
        }

        $alive_pids = [];

        foreach ($scanned_processes as $scanned_process) {
            $alive_pids[(int)$scanned_process["pid"]] = true;
        }

        return $alive_pids;
    }

    // -------------------------------------------------------------------------
    // 程序快照（活躍清單 ＋ 各層計數 ＋ daemon 起時——畫面資料的唯一來源）
    public function get_process_snapshot()
    {
        // 本輪掃描（存活集用）
        $scanned_processes = $this->get_scanned_processes();
        
        // 存活清單（失敗回 null＝沿用舊 tasklist 查法）
        $alive_pids = $this->collect_alive_pids($scanned_processes);
        
        // 心跳記錄（顯示活躍程序——以掃描存活集比對）
        $heartbeat_records = $this->read_heartbeat_records($alive_pids);
        
        $live_processes = [];
        $daemon_started = "";
        
        foreach ($heartbeat_records as $heartbeat_record) {
            // 新格式：program／program_label；舊檔：name／program（兼容一個週期）
            $process_name = (string)($heartbeat_record["program"] ?? $heartbeat_record["name"] ?? "");
            $started_fmt  = (string)($heartbeat_record["started_at_fmt"] ?? "");
            
            // 摘要列用：daemon 的啟動時間（鏈上第一顆常駐程序——兩agent任一）
            if ($process_name === "goose_daemon") {
                $daemon_started = $started_fmt;
            }
            
            $live_processes[] = [
                "name"       => $process_name,
                "program"    => (string)($heartbeat_record["program_label"] ?? $heartbeat_record["program"] ?? ""),
                "pid"        => (int)($heartbeat_record["pid"] ?? 0),
                "extra_text" => (string)($heartbeat_record["updated_at_fmt"] ?? ""),
            ];
        }
        
        // 排序：鏈序（bridge → daemon → worker → engine）→ pid
        usort($live_processes, function ($process_a, $process_b) {
            $rank_a = self::PROCESS_RANK[$process_a["name"]] ?? 9;
            $rank_b = self::PROCESS_RANK[$process_b["name"]] ?? 9;
            
            // 判定：不同層 → 依鏈序
            if ($rank_a !== $rank_b) {
                return $rank_a <=> $rank_b;
            }
            
            return $process_a["pid"] <=> $process_b["pid"];
        });
        
        // 各層計數（現行agent鏈——bridge → daemon → worker → engine；依鏈序）
        $daemon_engine  = hkai()->agent()->get_daemon_engine();
        $engine_chain   = self::ENGINE_CHAINS[$daemon_engine] ?? self::ENGINE_CHAINS["goose_daemon"];
        $process_counts = ["bridge" => 0, $engine_chain[0] => 0, "worker" => 0, $engine_chain[1] => 0];
        foreach ($live_processes as $live_process) {
            // 判定：名唔在現行agent鏈（異agent殘留）→ 不計
            if (!isset($process_counts[$live_process["name"]])) {
                continue;
            }

            $process_counts[$live_process["name"]]++;
        }
        
        $process_data = [];
        $process_data["live_processes"]     = $live_processes;
        $process_data["process_counts"]     = $process_counts;
        $process_data["daemon_started_fmt"] = $daemon_started;
        $process_data["alive_count"]        = count($live_processes);
        
        return $process_data;
    }
    // 心跳檔清單（process_status/bridge.*.json——每次現掃，唔用快取；等待新檔時用）
    public function list_bridge_heartbeat_files()
    {
        $process_status_dir = sc_get_config("hkai.process_status_dir");

        // 判定：目錄不存在 → 空清單
        if (!is_dir($process_status_dir)) {
            return [];
        }

        return glob($process_status_dir . "/bridge.*.json");
    }

    // 心跳檔路徑（process_status/bridge.*.json 第一份；冇＝空字串）
    // 每次全新掃描（唔快取——狀態判斷必須即時）
    public function get_bridge_heartbeat_file()
    {
        $heartbeat_paths = $this->list_bridge_heartbeat_files();

        // 判定：冇心跳檔 → 空
        if (count($heartbeat_paths) === 0) {
            return "";
        }

        return $heartbeat_paths[0];
    }

    // 快速判斷 bridge 是否在跑（只睇心跳檔 mtime——約 0.3µs；唔讀內容）
    public function is_bridge_alive($fresh_sec = 3)
    {
        $heartbeat_file = $this->get_bridge_heartbeat_file();

        // 判定：冇心跳檔 → 唔在跑
        if ($heartbeat_file === "") {
            return false;
        }

        $heartbeat_age_sec = time() - filemtime($heartbeat_file);

        return ($heartbeat_age_sec <= $fresh_sec);
    }

    // bridge 心跳記錄（process_status/bridge.<pid>.json——同一時間只有一個 server——只讀第一份）
    // 說明：純讀檔（唔經 bridge 自問自答）；殘檔／壞檔只記 log，清檔交清道夫
    public function read_bridge_heartbeat()
    {
        $heartbeat_file = $this->get_bridge_heartbeat_file();

        // 判定：冇心跳檔 → null
        if ($heartbeat_file === "") {
            return null;
        }

        $heartbeat_record = json_decode(@file_get_contents($heartbeat_file), true);

        // 判定：非合法 JSON（半寫入／壞檔）→ null＋記 log（讀取層唔刪檔）
        if (!is_array($heartbeat_record)) {
            hkai()->runtime()->logger()->warn("read_bridge_heartbeat: invalid heartbeat file — " . $heartbeat_file);

            return null;
        }

        $updated_at = (int)($heartbeat_record["updated_at"] ?? 0);

        // 判定：心跳過期（bridge 已死嘅殘檔）→ null＋記 log
        if ((time() - $updated_at) > self::STATUS_STALE_SEC) {
            hkai()->runtime()->logger()->warn("read_bridge_heartbeat: stale heartbeat (" . (time() - $updated_at) . "s) — " . $heartbeat_file);

            return null;
        }

        return $heartbeat_record;
    }

    // 讀心跳檔（每程序一檔——process_status/*.json）——以存活 pid 集比對；不活躍（或壞檔／心跳停滯）就刪檔
    // $alive_pids：bridge 掃描存活集（null＝逐個現查——bridge 失敗時的退路）
    public function read_heartbeat_records($alive_pids = null)
    {
        $heartbeat_records = [];
        
        $process_status_dir = sc_get_config("hkai.process_status_dir");
        
        // 判定：目錄不存在（尚無程序寫過心跳）→ 空
        if (!is_dir($process_status_dir)) {
            return $heartbeat_records;
        }
        
        $now_time = time();
        
        foreach (glob($process_status_dir . "/*.json") as $heartbeat_path) {
            $heartbeat_content = @file_get_contents($heartbeat_path);
            $heartbeat_record  = json_decode($heartbeat_content, true);
            
            // 判定：非合法 JSON（半寫入／壞檔）→ 清掉（活著的程序下一秒會自己再寫一份）
            if (!is_array($heartbeat_record)) {
                @unlink($heartbeat_path);
                continue;
            }
            
            $process_pid = (int)($heartbeat_record["pid"] ?? 0);
            $updated_at  = (int)($heartbeat_record["updated_at"] ?? 0);
            
            // 心跳年齡（秒——updated_at 缺失時視為極舊）
            $beat_age = PHP_INT_MAX;
            
            // 判定：updated_at 有效 → 以現在時間相減
            if ($updated_at > 0) {
                $beat_age = $now_time - $updated_at;
            }
            
            // PID 真的在？（有帶清單＝比對 bridge 掃描結果，免逐個 tasklist；未帶＝舊查法）
            $pid_alive = false;
            
            // 判定：有帶存活清單 → 比對集合
            if ($alive_pids !== null) {
                $pid_alive = isset($alive_pids[$process_pid]);
            }
            // 未帶清單 → 逐個現查（舊查法）
            else {
                $pid_alive = $this->is_process_alive($process_pid);
            }
            
            // 心跳新鮮？（未停滯過久）
            $beat_fresh = ($beat_age <= self::STATUS_STALE_SEC);
            
            // 判定：PID 在，且心跳新鮮 → 活躍
            $is_alive = false;
            
            if ($pid_alive) {
                if ($beat_fresh) {
                    $is_alive = true;
                }
            }
            
            // 判定：不活躍 → 刪殘檔（畫面只留活躍的）
            if (!$is_alive) {
                @unlink($heartbeat_path);
                continue;
            }
            
            $heartbeat_record["is_alive"] = $is_alive;
            $heartbeat_record["beat_age"] = $beat_age;
            
            $heartbeat_records[] = $heartbeat_record;
        }
        
        return $heartbeat_records;
    }
    
    // 終止程序（bridge → Python；先殺子、後殺父——子孫由清單的 pid／ppid 關係決定）
    public function kill_process($process_pid)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → false
        if ($process_pid < 1) {
            return false;
        }

        // 清單血緣：最深層先殺（先子後父）
        $child_pids = $this->collect_child_pids($process_pid);

        // 殺戳來源（邊個動作喺邊行落刀——帶埋去 bridge 殺戳日誌）
        $kill_source_text = $this->build_kill_source_text();

        $kill_data = hkai()->process()->bridge()->request("/process/kill/", [
            "pid"        => $process_pid,
            "child_pids" => $child_pids,
            "source"     => $kill_source_text,
        ]);

        // 判定：bridge 明確回報成功 → 完成
        $killed = (($kill_data["ok"] ?? false) === true);
        if ($killed) {
            return true;
        }

        // 判定：回應遺失（例如殺 bridge 自己——程序已死但回應返唔到）→ 現查 pid：已死＝成功
        if (!$this->is_process_alive($process_pid)) {
            return true;
        }

        return false;
    }

    // 砌殺戳來源（呼叫堆疊：呼叫端函數＠落刀行——格式：函數@檔名:行）
    private function build_kill_source_text()
    {
        $backtrace_frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        $caller_function = $backtrace_frames[2]["function"] ?? "";
        $caller_file     = isset($backtrace_frames[1]["file"]) ? basename($backtrace_frames[1]["file"]) : "";
        $caller_line     = $backtrace_frames[1]["line"] ?? 0;

        return $caller_function . "@" . $caller_file . ":" . $caller_line;
    }

    // 心跳檔認名（process_status 目錄——六個程序短名逐個查；任一命中＝本系統程序；kill 白名單第一關）
    public function has_status_heartbeat($process_pid)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → false
        if ($process_pid < 1) {
            return false;
        }

        $status_dir = sc_get_config("hkai.process_status_dir");

        // 逐個短名（bridge／goose_daemon／worker／goose）查心跳檔
        foreach (["bridge", "goose_daemon", "worker", "goose"] as $process_name) {
            // 判定：心跳檔存在 → 本系統程序
            if (is_file($status_dir . "/" . $process_name . "." . $process_pid . ".json")) {
                return true;
            }
        }

        return false;
    }


    // 由清單收集子孫 pid（遞迴；回傳 deepest-first——先子後父的殺序）
    private function collect_child_pids($process_pid)
    {
        $list_records = $this->read_list_records();
        $child_pids     = [];

        // 遞迴：先收孫、再收子（deepest-first）
        $this->collect_child_pids_recursive($list_records, (int)$process_pid, $child_pids);

        return $child_pids;
    }

    // 遞迴收集（$child_pids 以 by-reference 累積——孫在前、子在後）
    private function collect_child_pids_recursive($list_records, $parent_pid, &$child_pids)
    {
        foreach ($list_records as $list_record) {
            // 判定：ppid 不符此父 → 跳過
            if ((int)($list_record["ppid"] ?? 0) !== (int)$parent_pid) {
                continue;
            }

            $list_pid = (int)($list_record["pid"] ?? 0);

            // 判定：pid 無效／重複 → 跳過
            if ($list_pid < 1 || in_array($list_pid, $child_pids, true)) {
                continue;
            }

            // 先收它的子孫（最深層先入列）
            $this->collect_child_pids_recursive($list_records, $list_pid, $child_pids);

            $child_pids[] = $list_pid;
        }
    }

    // 讀清單全部記錄（陣列）
    private function read_list_records()
    {
        $list_path = sc_get_config("hkai.public.process_status.process_list");

        // 判定：路徑未定或檔案不存在 → 空
        if (!is_string($list_path)) {
            return [];
        }

        if (!is_file($list_path)) {
            return [];
        }

        $list_records = [];

        foreach (file($list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $list_line) {
            $list_record = json_decode($list_line, true);

            // 判定：非合法 JSON → 跳過
            if (!is_array($list_record)) {
                continue;
            }

            $list_records[] = $list_record;
        }

        return $list_records;
    }

}
