<?php

namespace sc\app\hkai\cls\sc_python_bridge;

// == sc_python_bridge_Class：python server 生命週期管理（singleton——經 bridging hkai__python_bridge() 取得） ==
// == 職責：start_server（拉起＋等 bridge 自報就緒）／stop_server（全清進程）／port_available（port 可用檢查） ==
// == 啟動結果（2026-10-23 定案）：PHP 拉起後輪詢 process_status/bridge.*.json 心跳（started_at ≥ 拉起時間＝成功） ==
// == 拉起前置（順序）：完整檢查（已起即回）→ 環境檢查 → 全清掃（停用同路——worker／aider_daemon／goose_daemon／openhands_api_daemon／bridge＋孤兒＋死檔）→ port 佔用檢查（netstat） ==
// == server 狀態四態（前端）：stopped（未啟用）／starting（啟用中）／running（已啟用）／stopping（停止中） ==

class sc_python_bridge_Class
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
    
    
    // bridge 狀態（簡版——取 check_is_python_bridge_running 結果，只回 server_status／is_alive）
    public function get_bridge_status()
    {
        $bridge_check = hkai__python_bridge_check()->check_is_python_bridge_running();

        $bridge_status = [];
        $bridge_status["server_status"] = $bridge_check["server_status"];
        $bridge_status["is_alive"]      = $bridge_check["is_alive"];

        return $bridge_status;
    }
    
    // 啟動 python server（拉起＋等 bridge 自報——回傳 ["ok" => bool, "message" => string]）
    // 流程：①完整檢查（已起即回錯誤）②全清掃（啟動＝前 session 已關——殘骸一律殺清：worker／aider_daemon／goose_daemon／openhands_api_daemon／bridge＋孤兒＋死檔）
    //       ③port 可用檢查（bind 測試）④拉起 ⑤輪詢 bridge 心跳
    public function start_server($server_host = "", $server_port = 0)
    {
        // python 執行檔／bridge 套件存在檢查已移除（10-13 用戶令）——啟動責任喺 launcher（exec 失敗即回 ok=false；python 存在性另由 runtime check 覆蓋）
        $runtime_config_file = hkai__sc_runtime()->get_runtime_config_file();

        // 目標 host／port（本次要佔嘅位——呼叫端帶入）
        // 判定：未帶 port → 自動揀一個可用埠
        if ($server_port <= 0) {
            $server_port = $this->find_available_server_port();

            // 判定：全段被佔用 → 失敗
            if ($server_port === 0) {
                return ["ok" => false, "message" => "找不到可用連接埠（6001–6999 全被佔用）"];
            }
        }

        // 判定：未帶 host → 回讀存檔值；存檔亦缺 → 本機
        if ($server_host === "") {
            $runtime_configs = hkai__core()->get_runtime_configs();
            $server_settings = $runtime_configs["server_settings"] ?? [];
            $server_host     = (string)($server_settings["host"] ?? "");

            // 判定：存檔無 host → 127.0.0.1
            if ($server_host === "") {
                $server_host = "127.0.0.1";
            }
        }

        $bridge_check = hkai__python_bridge_check()->check_is_python_bridge_running_full();

        // 判定：bridge 已啟動（心跳新鮮＋pid 在生）→ 回錯誤（要重啟請用 restart）
        if ($bridge_check["is_alive"]) {
            return ["ok" => false, "message" => "伺服器已經啟動"];
        }

        // 前置大清掃（啟動＝前一個 session 已關——殘留一律殺清：worker／aider_daemon／goose_daemon／openhands_api_daemon／bridge；同停用同路）
        $this->stop_server();

        // 孤兒清掃（心跳檔冇／已死都仲在跑嘅殘骸——程式名命中即殺：aider_daemon／goose_daemon／openhands_api_daemon 先、bridge 殿後）
        $orphan_pids = [];

        // 逐個程式名掃（aider_daemon.main／goose_daemon.main／bridge.main——同一把刀）
        foreach (["aider_daemon.main", "goose_daemon.main", "openhands_api_daemon.main", "bridge.main"] as $orphan_program_name) {
            // 擁有權過濾（只掃本實例 runtime 拉起嘅孤兒——唔准殺別個站）
            $program_pids = hkai__python_bridge_process()->find_python_pids_by_program_name_and_runtime($orphan_program_name, $runtime_config_file);

            // 逐個孤兒原生終止（程式名命中＋無主——唔留情）
            foreach ($program_pids as $orphan_pid) {
                hkai__python_bridge_process()->kill_process_native($orphan_pid);
                $orphan_pids[] = $orphan_pid;
            }
        }

        // 死檔清掃（心跳檔仲在、程序已死——例：worker.5576.json）
        hkai__python_bridge_process()->remove_dead_heartbeat_files();

        // 判定：有殺過孤兒 → 等 OS 收屍（port 放返出嚟——上限 3s）
        if (count($orphan_pids) > 0) {
            $this->wait_port_release($server_port, 3);
        }

        // port 可用檢查（試 bind 一次即放——唔可用＝已被佔用）
        $is_port_available = $this->port_available($server_host, $server_port);

        // 判定：port 唔可用 → 拒
        if ($is_port_available === false) {
            return ["ok" => false, "message" => "連接埠 " . $server_port . " 不可用（已被佔用）。請按「自動分配連接埠」改用可用埠。"];
        }

        // 記錄本次來源 origin（白名單——SSE CORS；去重附加）
        $this->append_origin_whitelist();

        // 寫入本次要起嘅 host／port（python 唯一設定來源——拉起前先寫，bridge 開機即讀）
        $server_settings_update = [];
        $server_settings_update["host"] = $server_host;
        $server_settings_update["port"] = $server_port;
        hkai__core()->write_runtime_configs("server_settings", $server_settings_update);

        $spawn_time = time();

        // 拉起 bridge（標準 launcher——關鍵字＋旗標；--bg＝非阻塞；runtime 路徑＝機器層值）
        // bridge 自理日誌：data/logs/bridge.error.log／bridge.audit.log
        $launcher_result = hkai__python_launcher()->exec([
            "bridge",
            "--bg",
        ]);

        // 判定：launcher 送出失敗
        if ($launcher_result["ok"] !== true) {
            return ["ok" => false, "message" => "server command failed"];
        }

        // 等待 bridge 心跳出現（每 300ms、上限 15s——心跳＝生存證明）
        $is_bridge_started = $this->wait_bridge_heartbeat(15, $spawn_time);

        // 判定：逾時冇新心跳 → 失敗
        if ($is_bridge_started === false) {
            return ["ok" => false, "message" => "啟動失敗：未收到 server 就緒回覆。"];
        }

        return ["ok" => true, "message" => "server started"];
    }

    // 等待新 bridge 心跳（process_status/bridge.*.json——started_at 唔早於拉起時間＝啟動成功；每 300ms，逾時回 false）
    private function wait_bridge_heartbeat($server_wait_timeout_sec, $not_before_time)
    {
        $wait_start_at = time();

        while (true) {
            $heartbeat_record = hkai__python_bridge_process()->read_bridge_heartbeat();

            // 判定：有最新心跳 → 核對係唔係本次拉起嘅程序
            if (is_array($heartbeat_record)) {
                $heartbeat_started_at = (int)($heartbeat_record["started_at"] ?? 0);

                // 判定：心跳開始時間唔早於拉起時間 → 新程序已起
                if ($heartbeat_started_at >= $not_before_time) {
                    return true;
                }
            }

            // 判定：逾時 → false
            if ((time() - $wait_start_at) >= $server_wait_timeout_sec) {
                return false;
            }

            usleep(300000);
        }
    }

    // 等 port 放返出嚟（剛被殺嘅運行體退場——每 300ms 查 netstat，上限 $server_wait_timeout_sec 秒）
    private function wait_port_release($server_port, $server_wait_timeout_sec)
    {
        $wait_start_at = time();

        while (true) {
            // 判定：查無 listener → 已放
            if ($this->find_listener_pid($server_port) === 0) {
                return;
            }
            // 判定：逾時 → 放棄等（後面嘅 port 檢查照樣會攔）
            if ((time() - $wait_start_at) >= $server_wait_timeout_sec) {
                return;
            }
            usleep(300000);
        }
    }

    // 停止 python server（全清本系統程序：worker → aider_daemon／goose_daemon／openhands_api_daemon → bridge，由深至淺）
    // 說明（2026-10-02 定案）：停用＝殺晒——舊版只殺 bridge，daemon／worker 會殘留
    public function stop_server()
    {
        $all_killed = true;

        // 殺序：深 → 淺（worker 先、bridge 殿後——總開關最後）
        foreach (["worker", "aider_daemon", "goose_daemon", "bridge"] as $process_name) {
            // 判定：該組有殺唔到嘅程序 → 整體轉 false
            if (!$this->kill_process_group($process_name)) {
                $all_killed = false;
            }
        }

        return $all_killed;
    }

    // 重啟 python server（停 → 起背靠背連做——先全清、等 port 放返、再照 start_server 拉起；回傳結構同 start_server）
    public function restart_server($server_host = "", $server_port = 0)
    {
        $this->stop_server();

        // 目標 port（未帶 → 停止後自動揀一個可用埠——同 start_server 一致）
        // 判定：未帶 port → 自動分配
        if ($server_port <= 0) {
            $server_port = $this->find_available_server_port();
        }

        // 等 port 放返出嚟（同一請求內背靠背拉起——被殺嘅 listener 退場需時）
        // 判定：有目標 port → 先等釋放（上限 5s）
        if ($server_port > 0) {
            $this->wait_port_release($server_port, 5);
        }

        return $this->start_server($server_host, $server_port);
    }

    // 殺一組同名程序（活躍 pid 逐個原生終止＋清心跳檔；回傳是否全清）
    private function kill_process_group($process_name)
    {
        $process_status_dir = hkai__core()->get_process_status_dir();
        $group_killed       = true;

        foreach (hkai__python_bridge_process()->find_heartbeat_pids($process_name) as $process_pid) {
            $killed = hkai__python_bridge_process()->kill_process_native($process_pid);

            // 判定：殺唔到（程序仍在）→ 記錄
            if (!$killed) {
                $group_killed = false;
            }

            // 清該程序心跳檔（唔等 60s 過期——畫面立即反映已停）
            @unlink($process_status_dir . "/" . $process_name . "." . $process_pid . ".json");
        }

        return $group_killed;
    }

    // 讀 server 日誌（offset 標記——0／無效＝全量 tail；有效＝只讀之後嘅新增）
    // 回 ["mode" => "full"|"append", "lines" => [行…]（時序舊→新）, "offset" => 新標記（最後完整行尾之位元組位置）]
    public function read_server_log_after_offset($log_byte_offset = 0, $log_line_limit = 500)
    {
        $python_script_dir = hkai__core()->get_python_script_dir();
        $audit_log_file    = dirname($python_script_dir) . "/data/logs/bridge.audit.log";

        $read_result = [];
        $read_result["mode"]   = "full";
        $read_result["lines"]  = [];
        $read_result["offset"] = 0;

        // 判定：日誌檔唔存在 → 空結果
        if (!is_file($audit_log_file)) {
            return $read_result;
        }

        $file_size = filesize($audit_log_file);

        // 判定：帶咗 offset（> 0）→ 核對未輪替
        if ($log_byte_offset > 0) {
            // 判定：標記位仍喺檔內（未輪替）→ 增量讀
            if ($log_byte_offset <= $file_size) {
                return $this->read_server_log_append($audit_log_file, $log_byte_offset, $log_line_limit);
            }
        }

        return $this->read_server_log_full($audit_log_file, $file_size, $log_line_limit);
    }

    // 全量讀（檔尾窗口——最後 $log_line_limit 行完整行；offset＝最後完整行尾）
    private function read_server_log_full($audit_log_file, $file_size, $log_line_limit)
    {
        $read_result = [];
        $read_result["mode"]   = "full";
        $read_result["lines"]  = [];
        $read_result["offset"] = 0;

        // 讀取窗口（檔尾最多 256KB；細檔＝由頭讀）
        $read_byte_limit  = 256 * 1024;
        $read_byte_offset = $file_size - $read_byte_limit;

        // 判定：窗口起點為負（細檔）→ 由頭讀
        if ($read_byte_offset < 0) {
            $read_byte_offset = 0;
        }

        $file_handle = fopen($audit_log_file, "rb");

        // 判定：開唔到（檔案鎖／權限）→ 空結果
        if ($file_handle === false) {
            return $read_result;
        }

        fseek($file_handle, $read_byte_offset);
        $raw_chunk = fread($file_handle, $read_byte_limit);
        fclose($file_handle);

        // 判定：讀唔到 → 空結果
        if ($raw_chunk === false) {
            return $read_result;
        }

        // 標記（檔尾係完整行 → offset＝檔尾；尾行寫緊 → 停喺最後換行之後）
        $last_newline_position = strrpos($raw_chunk, "\n");

        // 判定：冇任何換行（極短檔）→ offset 留 0（下輪全量再嚟）
        if ($last_newline_position === false) {
            return $read_result;
        }

        // 判定：最後換行＝埋尾（完整收尾）→ offset＝檔尾
        if ($last_newline_position === strlen($raw_chunk) - 1) {
            $read_result["offset"] = $file_size;
        } else {
            // 判定：尾行寫緊 → offset＝最後換行之後
            $read_result["offset"] = $file_size - (strlen($raw_chunk) - $last_newline_position - 1);
        }

        // 切行（CRLF／LF／CR 混用都食）
        $log_lines = preg_split("/\r\n|\n|\r/", $raw_chunk);

        // 判定：窗口由檔中間開始（首行係半截）→ 丟首行
        if ($read_byte_offset > 0) {
            array_shift($log_lines);
        }

        // 去尾端空元素（檔尾換行切出——免空行收尾）
        while (end($log_lines) === "") {
            array_pop($log_lines);
        }

        // 判定：尾行未完整（寫緊）→ 抽走尾行（下輪完整先交——免半截又重複）
        if ($read_result["offset"] < $file_size) {
            array_pop($log_lines);
        }

        // 取末段（最後 $log_line_limit 行）
        $log_lines = array_slice($log_lines, -$log_line_limit);

        $read_result["lines"] = $log_lines;

        return $read_result;
    }

    // 增量讀（由 offset 讀到檔尾——只回完整行；offset 推進到最後完整行尾）
    private function read_server_log_append($audit_log_file, $log_byte_offset, $log_line_limit)
    {
        $read_result = [];
        $read_result["mode"]   = "append";
        $read_result["lines"]  = [];
        $read_result["offset"] = $log_byte_offset;

        $file_handle = fopen($audit_log_file, "rb");

        // 判定：開唔到 → 原樣回（下輪再試）
        if ($file_handle === false) {
            return $read_result;
        }

        fseek($file_handle, $log_byte_offset);
        $raw_chunk = fread($file_handle, 1024 * 1024);
        fclose($file_handle);

        // 判定：讀唔到 → 原樣回
        if ($raw_chunk === false) {
            return $read_result;
        }

        // 判定：無新內容 → 原樣回
        if ($raw_chunk === "") {
            return $read_result;
        }

        // 只計完整行（單輪上限 1MB——積壓分輪追；尾段未完嘅一行留下輪）
        $last_newline_position = strrpos($raw_chunk, "\n");

        // 判定：成段都係未完一行 → 原樣回
        if ($last_newline_position === false) {
            return $read_result;
        }

        $complete_chunk = substr($raw_chunk, 0, $last_newline_position + 1);

        // 切行（CRLF／LF／CR 混用都食）
        $log_lines = preg_split("/\r\n|\n|\r/", $complete_chunk);

        // 去尾端空元素（尾換行切出）
        while (end($log_lines) === "") {
            array_pop($log_lines);
        }

        // 判定：行數超上限（極端積壓）→ 只留最新 $log_line_limit 行
        if (count($log_lines) > $log_line_limit) {
            $log_lines = array_slice($log_lines, -$log_line_limit);
        }

        $read_result["lines"]  = $log_lines;
        $read_result["offset"] = $log_byte_offset + strlen($complete_chunk);

        return $read_result;
    }

    // 通用 bridge 請求（POST /path——data 為 payload；$timeout_sec＝整個請求總時限（默認 30 秒——慢操作由呼叫端加大）；PHP 只負責傳參與讀 JSON）
    public function request($path, $data = [], $timeout_sec = 30)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        
        $server_host = $server_settings["host"];
        $server_port = $server_settings["port"];
        $request_url = "http://" . $server_host . ":" . $server_port . "/" . ltrim($path, "/");
        
        $curl_handle = curl_init($request_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT_MS, 300);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, $timeout_sec);
        $response_body = curl_exec($curl_handle);
        $http_code     = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);

        // curl 錯誤碼（curl_close 之後讀唔到——先記落嚟）
        $curl_errno = curl_errno($curl_handle);

        curl_close($curl_handle);
        
        // 判定：超時（時限到——操作可能仲喺橋嗰邊跑緊，唔等於橋壞）→ 失敗（帶時限秒數）
        if ($curl_errno === CURLE_OPERATION_TIMEDOUT) {
            return ["ok" => false, "error" => "bridge request timed out (" . $timeout_sec . "s)"];
        }

        // 判定：連唔上（bridge 未運行）→ 失敗
        if ($response_body === false) {
            return ["ok" => false, "error" => "python bridge not running"];
        }
        
        // 判定：HTTP 非 200（路由不存在／bridge 未更新／內部錯誤）→ 寫 log、回失敗
        if ($http_code !== 200) {
            hkai__sc_runtime_io()->warn("bridge request " . $path . " http " . $http_code);
            return ["ok" => false, "error" => "bridge request failed"];
        }
        
        $decoded = json_decode($response_body, true);
        
        // 判定：回應不可解析 → 失敗
        if (!is_array($decoded)) {
            return ["ok" => false, "error" => "invalid bridge output"];
        }
        
        return $decoded;
    }

    // 附加本次請求來源去 origin 白名單（storage/bridge/origin.whitelist；去重·LOCK_EX）
    private function append_origin_whitelist()
    {
        $origin = trim((string)($_SERVER["HTTP_ORIGIN"] ?? ""));

        // 判定：冇 Origin 標頭 → 由請求 host 組（本機 AJAX 可能無 Origin）
        if ($origin === "") {
            $request_host = trim((string)($_SERVER["HTTP_HOST"] ?? ""));

            // 判定：連 host 都冇（CLI）→ 唔寫
            if ($request_host === "") {
                return;
            }

            $request_scheme = "http";

            // 判定：HTTPS → https
            if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") {
                $request_scheme = "https";
            }

            $origin = $request_scheme . "://" . $request_host;
        }

        $origin = rtrim($origin, "/");

        $whitelist_file = hkai__core()->get_storage_dir() . "/bridge/origin.whitelist";

        // 判定：目錄唔存在 → 先建立
        if (!is_dir(dirname($whitelist_file))) {
            @mkdir(dirname($whitelist_file), 0777, true);
        }

        $existing_origins = [];

        // 判定：檔已存在 → 讀現有行
        if (is_file($whitelist_file)) {
            $existing_lines = file($whitelist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // 逐行收集（去空白）
            foreach ($existing_lines as $existing_line) {
                $existing_origins[] = trim($existing_line);
            }
        }

        // 判定：已存在 → 唔重複寫
        if (in_array($origin, $existing_origins, true)) {
            return;
        }

        file_put_contents($whitelist_file, $origin . chr(10), FILE_APPEND | LOCK_EX);
    }

    // 揀一個可用埠（6001–6999 第一個未監聽；0＝全段被佔）
    // port 可用檢查（試 bind 一次即放——成功＝可用；失敗＝被佔用）
    private function port_available($server_host, $server_port)
    {
        $probe_socket = @stream_socket_server("tcp://" . $server_host . ":" . $server_port, $probe_errno, $probe_errstr);

        // 判定：bind 失敗 → 不可用
        if ($probe_socket === false) {
            return false;
        }

        fclose($probe_socket);

        return true;
    }

    public function find_available_server_port($port_range_start = 6001, $port_range_end = 6999)
    {
        $listening_ports = $this->collect_listening_ports();

        // 逐個候選埠（範圍內第一個未被監聽）
        for ($port_candidate = $port_range_start; $port_candidate <= $port_range_end; $port_candidate++) {
            // 判定：已被監聽 → 下一個
            if (isset($listening_ports[$port_candidate])) {
                continue;
            }

            return $port_candidate;
        }

        return 0;
    }

    // 讀監聽中嘅 TCP 埠（netstat -ano -p tcp；回 [port => pid]）
    private function collect_listening_ports()
    {
        $listening_ports = [];

        $netstat_lines = [];
        @exec("netstat -ano -p tcp", $netstat_lines);

        // 逐行解析（LISTENING 行——本地位址尾段＝port）
        foreach ($netstat_lines as $netstat_line) {
            $netstat_columns = preg_split('/\s+/', trim($netstat_line));

            // 判定：欄位不足（表頭等）→ 跳
            if (count($netstat_columns) < 5) {
                continue;
            }

            // 判定：非 LISTENING → 跳
            if ($netstat_columns[3] !== "LISTENING") {
                continue;
            }

            $local_address = $netstat_columns[1];
            $address_parts = explode(":", $local_address);
            $port_text     = end($address_parts);

            // 判定：尾段非數字（IPv6 變體等）→ 跳
            if (!ctype_digit($port_text)) {
                continue;
            }

            $listening_ports[(int)$port_text] = (int)$netstat_columns[4];
        }

        return $listening_ports;
    }

    // 找該 port 的 listener PID（netstat -ano -p tcp——LISTENING 且本地位址以 :port 結尾；找不到回 0）
    private function find_listener_pid($server_port)
    {
        // 判定：無有效 port → 0
        if ($server_port <= 0) {
            return 0;
        }
        
        $netstat_lines = [];
        @exec("netstat -ano -p tcp", $netstat_lines);
        
        // 逐行尋找 LISTENING 行
        foreach ($netstat_lines as $netstat_line) {
            $netstat_columns = preg_split('/\s+/', trim($netstat_line));
            
            // 判定：欄位不足（表頭等）→ 跳過
            if (count($netstat_columns) < 5) {
                continue;
            }
            
            // 判定：非 LISTENING → 跳過
            if ($netstat_columns[3] !== "LISTENING") {
                continue;
            }
            
            // 判定：本地位址不以 :port 結尾 → 跳過
            if (substr($netstat_columns[1], -strlen(":" . $server_port)) !== ":" . $server_port) {
                continue;
            }
            
            return (int)$netstat_columns[4];
        }
        
        return 0;
    }
}
