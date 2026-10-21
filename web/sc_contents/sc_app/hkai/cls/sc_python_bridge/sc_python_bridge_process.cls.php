<?php

namespace sc\app\hkai\cls\sc_python_bridge;

// == sc_python_bridge_process_Class：本系統程序查詢（singleton——經 bridging hkai__python_bridge_process() 取得） ==
// == 職責：向 Python bridge 要「本系統程序即時清單」（/process/scan/）——存活判定與終止白名單的唯一來源
// ==       心跳純讀（read_bridge_heartbeat／collect_live_pids_by_name）＋原生終止（kill_process_native——bridge 死都行）＋殺戳日誌（write_kill_journal——落刀前記刀名／pid／映像名／來源）＋程式名掃描（find_python_pids_by_program_name——每請求一次 wmic 快照共用）
// ==       心跳檔（process_status/*.json——Python 每程序每秒寫）讀取（kill 白名單認名）；程序清單（process_status/process_list.jsonl）剔死（health 清潔工）＋血緣殺序 ==
// == program 定義：spawn 目標的點分路徑（對應 process_list 的 program 欄、process_status/*.json 第 3 欄） ==
// == 呈現（清單／計數／KILL 鈕）屬 sc_ai_settings 的 process 分頁；本檔只負責「問 Python」 ==

class sc_python_bridge_process_Class
{
    // 心跳停滯上限（秒——pid 還在但心跳停這麼久：pid 被重用或心跳執行緒卡死，仍視為死）
    const STATUS_STALE_SEC = 60;
    // 程序層級顯示順序（誰生誰的鏈序——計數與清單共用）
    const PROCESS_RANK = ["bridge" => 1, "aider_daemon" => 2, "goose_daemon" => 2, "openhands_api_daemon" => 2, "worker" => 3, "aider" => 4, "goose" => 4, "openhands_api" => 4];

    // 現行引擎鏈顯示名（daemon_engine 值 → [daemon 名, 引擎名]——計數摘要用）
    const ENGINE_CHAINS = ["aider_daemon" => ["aider_daemon", "aider"], "goose_daemon" => ["goose_daemon", "goose"], "openhands_api_daemon" => ["openhands_api_daemon", "openhands_api"]];
    
    private static $instance = null;

    // program 清單（bridge 精篩用——bridge／aider_daemon／goose_daemon／openhands_api_daemon 由 -m 起，worker／引擎（aider／goose／openhands_api）由血緣認）
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
            "aider_daemon.main",
            "goose_daemon.main",

            "openhands_api_daemon.main",
            "aider_daemon.worker",
            "goose_daemon.worker",

            "openhands_api_daemon.worker",
            "aider_entry.run_coder",
            "goose_entry.run_coder",

            "openhands_api_entry.run_coder",
        ];
    }

    // 掃描本機程序（bridge → Python；帶 program 清單精篩——根看 cmdline、子看血緣）
    public function get_scanned_processes()
    {
        $scan_data = hkai__python_bridge()->request("/process/scan/", [
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
    // 清潔工：剔走清單內嘅死程序行（health 每輪順手叫——殺程序從此唔改台帳）
    // 並發協議（多個 health 同時跑都安全——tmp 名帶 uid；原檔 checksum 比對）：
    //   1. 讀原檔、記低 checksum（之後比對——期間有人 append 就放棄本輪）
    //   2. 逐行判活：心跳新鮮＝活；心跳缺席＝現查 pid（免誤剔真活）
    //   3. 冇死行 → 零寫入（唔動檔）
    //   4. 有死行 → 寫入 process_list_{uid}.tmp
    //   5. 重讀原檔 checksum、同第 1 步比對
    //   6. 唔一致（期間有人 append）→ 清 tmp、本輪唔剔
    //   7. 一致 → rename 原子替換上檔
    //   8. 無論如何最後清 tmp（成功＝無檔可清；失敗／放棄＝執手尾）
    // 回傳：["checked" => 行數, "removed" => 剔走數, "is_rewritten" => 有冇寫檔]
    // -------------------------------------------------------------------------
    public function sweep_dead_process_lines()
    {
        $list_path = hkai__core()->get_process_list_path();

        // 判定：路徑未定 → 零事
        if (!is_string($list_path)) {
            return ["checked" => 0, "removed" => 0, "is_rewritten" => false];
        }

        // 判定：檔案不存在 → 零事
        if (!is_file($list_path)) {
            return ["checked" => 0, "removed" => 0, "is_rewritten" => false];
        }

        // 1. 讀原檔（checksum＝比對基準；行＝之後照抄保留）
        $original_checksum = md5_file($list_path);
        $list_lines        = file($list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 判定：讀唔到 → 零事
        if (!is_array($list_lines)) {
            return ["checked" => 0, "removed" => 0, "is_rewritten" => false];
        }

        // 判活前置：一次掃齊本輪新鮮心跳 pid（免逐行讀檔——微秒級）
        $fresh_heartbeat_pids = $this->collect_fresh_heartbeat_pids();

        $kept_lines    = [];
        $checked_count = 0;
        $removed_count = 0;

        // 2. 逐行判活——活嘅保留原文、死嘅剔走
        foreach ($list_lines as $list_line) {
            $checked_count++;

            $list_record = json_decode($list_line, true);

            // 判定：非合法 JSON → 原樣保留（清理不丟不明資料）
            if (!is_array($list_record)) {
                $kept_lines[] = $list_line;
                continue;
            }

            $list_pid = (int)($list_record["pid"] ?? 0);

            // 判活：心跳新鮮（已驗存活）→ 活
            if (isset($fresh_heartbeat_pids[$list_pid])) {
                $kept_lines[] = $list_line;
                continue;
            }

            // 判活：心跳缺席（可能啱死冇耐、未過門檻）→ 現查 pid；活嘅保留
            if (hkai__core()->is_process_alive($list_pid)) {
                $kept_lines[] = $list_line;
                continue;
            }

            // 死行 → 剔走
            $removed_count++;
        }

        // 3. 冇死行 → 零寫入
        if ($removed_count === 0) {
            return ["checked" => $checked_count, "removed" => 0, "is_rewritten" => false];
        }

        // 4. 寫入新檔（tmp 名帶 uid——防多個 health 同時跑撞檔）
        $list_tmp_path = dirname($list_path) . "/process_list_" . uniqid() . ".tmp";
        $list_content  = implode("\n", $kept_lines);

        // 判定：仍有內容 → 尾端補換行（維持 JSONL 行尾慣例）
        if ($list_content !== "") {
            $list_content .= "\n";
        }

        file_put_contents($list_tmp_path, $list_content);

        // 5. 重讀原檔 checksum
        $current_checksum = md5_file($list_path);

        // 6. 唔一致（期間有人 append）→ 清 tmp、本輪唔剔
        if ($current_checksum !== $original_checksum) {
            @unlink($list_tmp_path);
            return ["checked" => $checked_count, "removed" => 0, "is_rewritten" => false];
        }

        // 7. 一致 → rename 原子替換
        $is_rewritten = @rename($list_tmp_path, $list_path);

        // 8. 無論如何最後清 tmp（成功＝已無此檔；失敗＝執手尾）
        @unlink($list_tmp_path);

        // 判定：rename 失敗 → 當本輪冇剔
        if (!$is_rewritten) {
            return ["checked" => $checked_count, "removed" => 0, "is_rewritten" => false];
        }

        return ["checked" => $checked_count, "removed" => $removed_count, "is_rewritten" => true];
    }

    // 收集本輪新鮮心跳 pid 集（process_status/*.json——updated_at 未過門檻＝活）
    // 用途：清潔工判活快速路（命中＝免現查）；心跳過期＝返現查 pid 把關（唔盲信）
    private function collect_fresh_heartbeat_pids()
    {
        $fresh_heartbeat_pids = [];

        $process_status_dir = hkai__core()->get_process_status_dir();

        // 判定：目錄不存在（尚無程序寫過心跳）→ 空集
        if (!is_dir($process_status_dir)) {
            return $fresh_heartbeat_pids;
        }

        $now_time = time();

        foreach (glob($process_status_dir . "/*.json") as $heartbeat_path) {
            $heartbeat_record = json_decode(@file_get_contents($heartbeat_path), true);

            // 判定：非合法 JSON（半寫入／壞檔）→ 跳過（返現查 pid 把關）
            if (!is_array($heartbeat_record)) {
                continue;
            }

            $updated_at = (int)($heartbeat_record["updated_at"] ?? 0);

            // 判定：心跳新鮮（未過門檻）→ 記入
            if (($now_time - $updated_at) <= self::STATUS_STALE_SEC) {
                $process_pid = (int)($heartbeat_record["pid"] ?? 0);

                // 判定：pid 有效 → 記入
                if ($process_pid > 0) {
                    $fresh_heartbeat_pids[$process_pid] = true;
                }
            }
        }

        return $fresh_heartbeat_pids;
    }

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
            $process_name = (string)($heartbeat_record["name"] ?? "");
            $started_fmt  = (string)($heartbeat_record["started_at_fmt"] ?? "");
            
            // 摘要列用：daemon 的啟動時間（鏈上第一顆常駐程序——兩引擎任一）
            if (in_array($process_name, ["aider_daemon", "goose_daemon"], true)) {
                $daemon_started = $started_fmt;
            }
            
            $live_processes[] = [
                "name"       => $process_name,
                "program"    => (string)($heartbeat_record["program"] ?? ""),
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
        
        // 各層計數（現行引擎鏈——bridge → daemon → worker → engine；依鏈序）
        $daemon_engine  = hkai__core()->get_daemon_engine();
        $engine_chain   = self::ENGINE_CHAINS[$daemon_engine] ?? self::ENGINE_CHAINS["aider_daemon"];
        $process_counts = ["bridge" => 0, $engine_chain[0] => 0, "worker" => 0, $engine_chain[1] => 0];
        foreach ($live_processes as $live_process) {
            // 判定：名唔在現行引擎鏈（異引擎殘留）→ 不計
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
    // bridge 的統一心跳記錄（process_status/bridge.*.json——檔案存在＋心跳新鮮＝在跑；過期殘檔即清）
    // 說明（2026-10-02 定案）：純讀檔——唔再經 bridge 自問自答（bridge 死咗都判得死）；多份取最新鮮
    public function read_bridge_heartbeat()
    {
        $process_status_dir = hkai__core()->get_process_status_dir();

        // 判定：目錄不存在（從未有程序寫過心跳）→ null
        if (!is_dir($process_status_dir)) {
            return null;
        }

        $now_time      = time();
        $latest_record = null;
        $latest_update = 0;

        foreach (glob($process_status_dir . "/bridge.*.json") as $heartbeat_path) {
            $heartbeat_record = json_decode(@file_get_contents($heartbeat_path), true);

            // 判定：非合法 JSON（半寫入／壞檔）→ 清掉（活著的程序下一秒會自己再寫一份）
            if (!is_array($heartbeat_record)) {
                @unlink($heartbeat_path);
                continue;
            }

            $updated_at = (int)($heartbeat_record["updated_at"] ?? 0);

            // 判定：心跳過期（bridge 已死嘅殘檔）→ 清掉
            if (($now_time - $updated_at) > self::STATUS_STALE_SEC) {
                @unlink($heartbeat_path);
                continue;
            }

            // 判定：比手上嘅更新鮮 → 換（多份時取最新一份）
            if ($updated_at > $latest_update) {
                $latest_record = $heartbeat_record;
                $latest_update = $updated_at;
            }
        }

        return $latest_record;
    }

    // 讀心跳檔（每程序一檔——process_status/*.json）——以存活 pid 集比對；不活躍（或壞檔／心跳停滯）就刪檔
    // $alive_pids：bridge 掃描存活集（null＝逐個現查——bridge 失敗時的退路）
    public function read_heartbeat_records($alive_pids = null)
    {
        $heartbeat_records = [];
        
        $process_status_dir = hkai__core()->get_process_status_dir();
        
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
                $pid_alive = hkai__core()->is_process_alive($process_pid);
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

        $kill_data = hkai__python_bridge()->request("/process/kill/", [
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
        if (!hkai__core()->is_process_alive($process_pid)) {
            return true;
        }

        return false;
    }

    // 原生強制終止（PHP 直接 taskkill／kill——唔經 bridge；bridge 已死／要殺 bridge 自己都用得）
    public function kill_process_native($process_pid)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → false
        if ($process_pid < 1) {
            return false;
        }

        // 落刀前寫殺戳日誌（刀名／pid／映像名／來源——失敗唔影響落刀）
        $kill_source_text = $this->build_kill_source_text();
        $this->write_kill_journal("php:kill_process_native", $process_pid, $kill_source_text);

        $kill_command   = "kill -9 " . $process_pid . " 2>&1";
        $kill_exit_code = 0;
        $kill_output    = [];

        // 判定：Windows → taskkill（/T 連子孫一齊——先子後父由 OS 層做）
        if (PHP_OS_FAMILY === "Windows") {
            $kill_command = "taskkill /F /T /PID " . $process_pid . " 2>&1";
        }

        @exec($kill_command, $kill_output, $kill_exit_code);

        // 判定：命令報錯（例：程序已唔在）→ 以現查為準（已死＝達成；仲在＝失敗）
        if ($kill_exit_code !== 0) {
            return !hkai__core()->is_process_alive($process_pid);
        }

        return true;
    }

    // 寫殺戳日誌（append 一行 JSON 到 process_status/kill_journal.jsonl——落刀前記錄；失敗唔影響落刀）
    private function write_kill_journal($knife_name, $process_pid, $source_text)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → 唔記
        if ($process_pid < 1) {
            return;
        }

        $kill_journal_record = [];
        $kill_journal_record["ts"]     = date("Y-m-d H:i:s");
        $kill_journal_record["knife"]  = $knife_name;
        $kill_journal_record["pid"]    = $process_pid;
        $kill_journal_record["image"]  = $this->find_process_image_name($process_pid);
        $kill_journal_record["source"] = $source_text;

        $kill_journal_path = hkai__core()->get_process_status_dir() . "/kill_journal.jsonl";
        $kill_journal_line = json_encode($kill_journal_record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        @file_put_contents($kill_journal_path, $kill_journal_line, FILE_APPEND | LOCK_EX);
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

    // 查目標程序映像名（Windows：tasklist 單查——同 hkai_core 存活查詢同一道命令；搵唔到＝空字串）
    private function find_process_image_name($process_pid)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → 空
        if ($process_pid < 1) {
            return "";
        }

        // Linux：/proc 原生（零子進程）
        if (PHP_OS_FAMILY === "Linux") {
            $exe_path = @readlink("/proc/" . $process_pid . "/exe");

            // 判定：讀到 → 檔名
            if ($exe_path !== false) {
                return basename($exe_path);
            }

            return "";
        }

        // 判定：非 Windows（macOS 等）→ 唔查
        if (PHP_OS_FAMILY !== "Windows") {
            return "";
        }

        $tasklist_lines = [];
        @exec('tasklist /FI "PID eq ' . $process_pid . '" /FO CSV /NH 2>nul', $tasklist_lines);

        $found_task_text = trim($tasklist_lines[0] ?? "");

        // 判定：唔以引號開頭（無輸出／本地化提示行）→ 空
        if (substr($found_task_text, 0, 1) !== '"') {
            return "";
        }

        $second_quote_pos = strpos($found_task_text, '"', 1);

        // 判定：冇第二個引號 → 空
        if ($second_quote_pos === false) {
            return "";
        }

        return substr($found_task_text, 1, $second_quote_pos - 1);
    }

    // 收集某短名下嘅活躍 pid（process_status/{name}.*.json——心跳新鮮先算；回 pid 陣列）
    public function collect_live_pids_by_name($process_name)
    {
        $process_status_dir = hkai__core()->get_process_status_dir();
        $process_pids       = [];

        // 判定：目錄不存在 → 空
        if (!is_dir($process_status_dir)) {
            return $process_pids;
        }

        $now_time = time();

        foreach (glob($process_status_dir . "/" . $process_name . ".*.json") as $heartbeat_path) {
            $heartbeat_record = json_decode(@file_get_contents($heartbeat_path), true);

            // 判定：非合法 JSON → 跳過（殘檔由各讀取端清理）
            if (!is_array($heartbeat_record)) {
                continue;
            }

            $updated_at = (int)($heartbeat_record["updated_at"] ?? 0);

            // 判定：心跳過期（程序已死）→ 跳過
            if (($now_time - $updated_at) > self::STATUS_STALE_SEC) {
                continue;
            }

            $process_pids[] = (int)($heartbeat_record["pid"] ?? 0);
        }

        return $process_pids;
    }

    // 死檔清掃（process_status 心跳檔：{worker|aider_daemon|goose_daemon|bridge}.{pid}.json 而程序已死 → 清走；.tmp／未死一律保留）
    public function remove_dead_heartbeat_files()
    {
        $process_status_dir = hkai__core()->get_process_status_dir();

        // 判定：目錄不存在 → 冇事
        if (!is_dir($process_status_dir)) {
            return;
        }

        $heartbeat_names = ["worker", "aider_daemon", "goose_daemon", "bridge"];

        // 逐個家族掃（worker／aider_daemon／goose_daemon／openhands_api_daemon／bridge——同一命名格式）
        foreach ($heartbeat_names as $heartbeat_name) {
            // 逐檔核 pid（{家族}.{pid}.json——程序死咗即清）
            foreach (glob($process_status_dir . "/" . $heartbeat_name . ".*.json") as $heartbeat_path) {
                $heartbeat_name_parts = explode(".", basename($heartbeat_path));
                $heartbeat_pid        = (int)($heartbeat_name_parts[1] ?? 0);

                // 判定：檔名解唔出 pid → 跳過（唔係心跳檔格式）
                if ($heartbeat_pid < 1) {
                    continue;
                }

                // 判定：程序仍在 → 保留（未死唔清）
                if (hkai__core()->is_process_alive($heartbeat_pid)) {
                    continue;
                }

                @unlink($heartbeat_path);
            }
        }
    }

    // 用程式名搵 python 程序 pid（清掃無心跳檔嘅殘骸用；搵唔到回空陣列）
    public function find_python_pids_by_program_name($program_name)
    {
        // 判定：非 Windows（wmic 唔存在）→ 走 ps 版
        if (PHP_OS_FAMILY !== "Windows") {
            return $this->find_pids_by_program_name_ps($program_name);
        }

        // 掃描記錄（wmic 一次快照——本請求內多個程式名共用，唔重複跑）
        $python_process_records = $this->read_python_process_records();

        $process_pids = [];

        // 逐記錄過濾（命令列含目標名＝命中）
        foreach ($python_process_records as $python_process_record_item) {
            $command_line = $python_process_record_item["command_line"];

            // 判定：命令列唔含目標名 → 跳過
            if (strpos($command_line, $program_name) === false) {
                continue;
            }

            $process_pids[] = $python_process_record_item["pid"];
        }

        return $process_pids;
    }

    // 同程式名＋指定 runtime 檔（擁有權過濾——只認自己個實例）
    public function find_python_pids_by_program_name_and_runtime($program_name, $runtime_config_file)
    {
        $runtime_config_file = str_replace("\\", "/", $runtime_config_file);
        $runtime_config_file = strtolower($runtime_config_file);

        $python_process_records = $this->read_python_process_records();

        $process_pids = [];

        // 逐記錄過濾（命令列含程式名＋自己 runtime 路徑＝命中）
        foreach ($python_process_records as $python_process_record_item) {
            $command_line = str_replace("\\", "/", $python_process_record_item["command_line"]);
            $command_line = strtolower($command_line);

            // 判定：唔含程式名 → 跳過
            if (strpos($command_line, $program_name) === false) {
                continue;
            }

            // 判定：唔含自己 runtime 路徑 → 跳過（別個實例）
            if (strpos($command_line, $runtime_config_file) === false) {
                continue;
            }

            $process_pids[] = $python_process_record_item["pid"];
        }

        return $process_pids;
    }

    // 讀本機 Python 程序記錄（wmic 一次攞晒 pid＋命令列——本請求內快取重用；回 [["pid"=>, "command_line"=>], …]）
    private function read_python_process_records()
    {
        // 判定：本請求已掃過 → 重用快照（唔重複跑 wmic）
        if ($this->python_process_records_cache !== null) {
            return $this->python_process_records_cache;
        }

        // wmic：只取 python 程序（name 過濾）＋列出命令列同 pid（/format:list＝一行一欄，好過 CSV）
        $wmic_lines = [];
        @exec('wmic process where "name=\'python.exe\'" get processid,commandline /format:list 2>&1', $wmic_lines);

        $python_process_records = [];
        $current_command_line   = null;

        foreach ($wmic_lines as $wmic_line) {
            $wmic_line = trim($wmic_line);

            // 判定：CommandLine 行 → 記住本程序命令列（待配對 pid）
            if (stripos($wmic_line, "CommandLine=") === 0) {
                $current_command_line = $wmic_line;
                continue;
            }

            // 判定：ProcessId 行（緊隨命令列）→ 成對收錄
            if (stripos($wmic_line, "ProcessId=") === 0) {
                // 判定：未記低命令列（資料唔成對）→ 跳過
                if ($current_command_line !== null) {
                    $python_process_records[] = [
                        "pid"          => (int)substr($wmic_line, strlen("ProcessId=")),
                        "command_line" => $current_command_line,
                    ];
                    $current_command_line = null;
                }
            }
        }

        $this->python_process_records_cache = $python_process_records;

        return $python_process_records;
    }

    // 用程式名搵 pid（ps 版——Linux／macOS；回空陣列＝搵唔到）
    private function find_pids_by_program_name_ps($program_name)
    {
        $process_pids = [];

        $ps_lines = [];
        @exec("ps -eo pid=,args=", $ps_lines);

        foreach ($ps_lines as $ps_line) {
            // 判定：命令列唔含目標名 → 跳過
            if (strpos($ps_line, $program_name) === false) {
                continue;
            }

            $ps_columns = preg_split('/\s+/', trim($ps_line), 2);

            // 判定：欄位不足 → 跳過
            if (count($ps_columns) < 2) {
                continue;
            }

            $process_pids[] = (int)$ps_columns[0];
        }

        return $process_pids;
    }

    // 心跳檔認名（process_status 目錄——六個程序短名逐個查；任一命中＝本系統程序；kill 白名單第一關）
    public function has_status_heartbeat($process_pid)
    {
        $process_pid = (int)$process_pid;

        // 判定：pid 無效 → false
        if ($process_pid < 1) {
            return false;
        }

        $status_dir = hkai__core()->get_process_status_dir();

        // 逐個短名（bridge／aider_daemon／goose_daemon／openhands_api_daemon／worker／aider／goose／openhands_api）查心跳檔
        foreach (["bridge", "aider_daemon", "goose_daemon", "openhands_api_daemon", "worker", "aider", "goose", "openhands_api"] as $process_name) {
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
        $list_path = hkai__core()->get_process_list_path();

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
