<?php

namespace sc\app\hkai\cls\sc_python_bridge;

// == sc_python_bridge_Class：python server 生命週期管理（singleton——經 bridging hkai__python_bridge() 取得） ==
// == 職責：start_server（拉起＋等 bridge 自報就緒）／stop_server（全清進程）／check_server_alive（HTTP 存活檢查） ==
// == 啟動結果（2026-10-02 定案）：bridge 開機自己寫 server_status.json（running／failed＋原因）——PHP 拉起後輪詢讀 ==
// == 拉起前置（順序）：完整檢查（已起即回）→ 環境檢查 → 全清掃（停用同路——worker／aider_daemon／goose_daemon／bridge＋孤兒＋死檔）→ port 佔用檢查（netstat） ==
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
    // 流程：①完整檢查（已起即回）②全清掃（啟動＝前 session 已關——殘骸一律殺清：worker／aider_daemon／goose_daemon／bridge＋孤兒＋死檔）
    //       ③port 佔用檢查（netstat listener——其他程序霸住即拒）④清舊結果檔 → 拉起 ⑤輪詢 server_status.json
    public function start_server($server_host = "", $server_port = 0)
    {
        // python 執行檔／bridge 套件存在檢查已移除（10-13 用戶令）——啟動責任喺 launcher（exec 失敗即回 ok=false；python 存在性另由 runtime check 覆蓋）
        $runtime_config_file = hkai__sc_runtime()->get_runtime_config_file();

        // 目標 host／port（本次要佔嘅位——呼叫端帶入）
        // 判定：未帶 port → 回讀存檔值（向後相容）
        if ($server_port <= 0) {
            $server_port = $this->get_server_port();
        }

        // 判定：未帶 host → 回讀存檔值（向後相容）
        if ($server_host === "") {
            $runtime_configs = hkai__core()->get_runtime_configs();
            $server_settings = $runtime_configs["server_settings"] ?? [];
            $server_host     = (string)$server_settings["host"];
        }

        $bridge_check = hkai__python_bridge_check()->check_is_python_bridge_running_full();

        // 判定：完整檢查報 alive → 先驗身份（同一部機可能有另一個站嘅 bridge）
        if ($bridge_check["is_alive"]) {
            $bridge_health = $this->read_bridge_health($server_host, $server_port);

            $bridge_owner_storage_dir = (string)($bridge_health["storage_dir"] ?? "");

            $runtime_configs = hkai__core()->get_runtime_configs();
            $own_storage_dir = (string)($runtime_configs["storage_dir"] ?? "");

            // 判定：身份唔同（別個 HKAI 實例佔住呢個埠）→ 報錯（唔准當自己已運行）
            if ($bridge_owner_storage_dir !== "" && $bridge_owner_storage_dir !== $own_storage_dir) {
                return ["ok" => false, "message" => "連接埠 " . $server_port . " 已被另一個 HKAI 實例佔用（" . $bridge_owner_storage_dir . "）。請按「自動分配連接埠」改用可用埠。"];
            }

            return ["ok" => true, "message" => "server already running"];
        }

        // 前置大清掃（啟動＝前一個 session 已關——殘留一律殺清：worker／aider_daemon／goose_daemon／bridge；同停用同路）
        $this->stop_server();

        // 孤兒清掃（心跳檔冇／已死都仲在跑嘅殘骸——程式名命中即殺：aider_daemon／goose_daemon 先、bridge 殿後）
        $orphan_pids = [];

        // 逐個程式名掃（aider_daemon.main／goose_daemon.main／bridge.main——同一把刀）
        foreach (["aider_daemon.main", "goose_daemon.main", "bridge.main"] as $orphan_program_name) {
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

        // port 佔用檢查（唔可以喺其他程序霸住嘅 port 上拉起——Windows 會照 bind 搶人個 port，必拒）
        $listener_pid = $this->find_listener_pid($server_port);

        // 判定：port 已被佔用（上面已排除我哋 bridge）→ 拒
        if ($listener_pid > 0) {
            return ["ok" => false, "message" => "連接埠 " . $server_port . " 已被其他程序佔用（PID " . $listener_pid . "）。請按「自動分配連接埠」改用可用埠。"];
        }

        // 寫入本次要起嘅 host／port（python 唯一設定來源——拉起前先寫，bridge 開機即讀）
        $server_settings_update = [];
        $server_settings_update["host"] = $server_host;
        $server_settings_update["port"] = $server_port;
        hkai__core()->write_runtime_configs("server_settings", $server_settings_update);

        // 清舊啟動結果檔（bridge 開機後會自己寫新一份——保底：連清唔走都靠寫入時間閘）
        @unlink(hkai__core()->get_server_status_path());

        $spawn_time = time();

        // 拉起 bridge（標準 launcher——關鍵字＋旗標；--bg＝非阻塞；runtime 路徑＝機器層值）
        // bridge 自理日誌：bridge/runtime/error.log／audit.log
        $launcher_result = hkai__python_launcher()->exec([
            "bridge",
            "--bg",
            "--runtime", $runtime_config_file,
        ]);

        // 判定：launcher 送出失敗
        if ($launcher_result["ok"] !== true) {
            return ["ok" => false, "message" => "server command failed"];
        }

        // 等待 bridge 自報（輪詢 server_status.json——每 300ms、上限 15s）
        $server_status = $this->wait_server_status(15, $spawn_time);

        // 判定：逾時未收到任何回覆 → 失敗
        if ($server_status === null) {
            return ["ok" => false, "message" => "啟動失敗：未收到 server 就緒回覆。"];
        }

        $status_value = (string)($server_status["status"] ?? "");

        // 判定：bridge 報 failed → 失敗（原樣帶上失敗原因）
        if ($status_value === "failed") {
            return ["ok" => false, "message" => "啟動失敗：" . (string)($server_status["reason"] ?? "unknown")];
        }

        return ["ok" => true, "message" => "server started"];
    }

    // 等待 bridge 自報啟動結果（輪詢 server_status.json——每 300ms、上限 $server_wait_timeout_sec；逾時回 null）
    private function wait_server_status($server_wait_timeout_sec, $not_before_time)
    {
        $wait_start_at = time();

        while (true) {
            $server_status = $this->read_server_status($not_before_time);

            // 判定：收到結果（running／failed）→ 回報
            if ($server_status !== null) {
                return $server_status;
            }
            // 判定：逾時 → null（bridge 冇回覆）
            if ((time() - $wait_start_at) >= $server_wait_timeout_sec) {
                return null;
            }
            usleep(300000);
        }
    }

    // 讀啟動結果檔（只認 $not_before_time 之後寫嘅一份——清唔走嘅舊檔唔會誤判；唔存在／非法回 null）
    private function read_server_status($not_before_time)
    {
        $server_status_path = hkai__core()->get_server_status_path();

        // 判定：檔案唔存在 → null
        if (!is_file($server_status_path)) {
            return null;
        }

        // 判定：舊檔（拉起之前寫嘅）→ null
        if (filemtime($server_status_path) < $not_before_time) {
            return null;
        }

        $server_status = json_decode(@file_get_contents($server_status_path), true);

        // 判定：非合法 JSON → null
        if (!is_array($server_status)) {
            return null;
        }

        return $server_status;
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

    // 停止 python server（全清本系統程序：worker → aider_daemon／goose_daemon → bridge，由深至淺）
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

        // 清啟動結果檔（本次運行嘅紀錄——下次啟動重新寫）
        @unlink(hkai__core()->get_server_status_path());

        return $all_killed;
    }

    // 重啟 python server（停 → 起背靠背連做——先全清、等 port 放返、再照 start_server 拉起；回傳結構同 start_server）
    public function restart_server($server_host = "", $server_port = 0)
    {
        $this->stop_server();

        // 目標 port（未帶 → 回讀存檔值——同 start_server 一致）
        // 判定：未帶 port → 回讀存檔值
        if ($server_port <= 0) {
            $server_port = $this->get_server_port();
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

        // 自己個 runtime（擁有權界線——只殺本實例拉起嘅程序，唔准殺別個站）
        $runtime_config_file = hkai__sc_runtime()->get_runtime_config_file();

        foreach (hkai__python_bridge_process()->find_python_pids_by_program_name_and_runtime($process_name, $runtime_config_file) as $process_pid) {
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
        $audit_log_file    = $python_script_dir . "/bridge/runtime/audit.log";

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

    // == 技術備註（timeout 語意——調整前先讀） ==
    // == ・CONNECTTIMEOUT_MS＝只限 TCP 連接階段；connect 成功即刻返回（本地 <1ms），不會用滿上限 ==
    // == ・TIMEOUT＝整個請求總時限（connect＋發送＋等回應＋收 body）——超過即中止 ==
    // == ・本機（127.0.0.1）：connect 逾時可細（150ms）；無 listener 時 OS 層約 1s 先拒——設細＝失敗時更快放棄 ==
    // == ・遠端／高並發：兩者都要調大（WAN connect 1-3s、總時限 5-10s）——否則慢網絡／排隊會被誤判「死」 ==
    // == ・連接成功 ≠ 有內容：有 body 先可判內容（2xx＋status ok）；body 等幾久＝TIMEOUT ==
    // == ・設定位置：plugins/sc_ai_health/__init__.php（server_check_timeout_ms／server_check_total_timeout_sec） ==
    
    // server 存活檢查（server_check_method：curl＝HTTP 驗內容／tcp＝快、只驗 port；timeout_ms＝connect 逾時）
    public function check_server_alive($server_check_method = "curl", $server_check_timeout_ms = 150, $server_check_total_timeout_sec = 2)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        
        $server_host        = $server_settings["host"];
        $server_port        = $server_settings["port"];
        // health 路徑＝固定契約（兩端硬編碼同一值——唔隨設定）
        $server_health_path = "/health";
        
        // 判定：tcp（只驗 port——connect 得上即生）
        if ($server_check_method === "tcp") {
            return $this->check_server_alive_by_tcp($server_host, $server_port, $server_check_timeout_ms);
        }
        
        // curl（HTTP GET health——驗證回應內容）
        return $this->check_server_alive_by_curl($server_host, $server_port, $server_health_path, $server_check_timeout_ms, $server_check_total_timeout_sec);
    }
    
    // aider_daemon 存活檢查（特殊命令——HTTP GET /daemon_alive；由 python server 本機原生查 pid 後回報）
    public function check_daemon_alive($server_check_timeout_ms = 150, $server_check_total_timeout_sec = 2)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        
        $server_host      = $server_settings["host"];
        $server_port      = $server_settings["port"];
        $daemon_alive_url = "http://" . $server_host . ":" . $server_port . "/daemon_alive";
        
        $curl_handle = curl_init($daemon_alive_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT_MS, $server_check_timeout_ms);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, $server_check_total_timeout_sec);
        $response_body = curl_exec($curl_handle);
        curl_close($curl_handle);
        
        // 判定：連唔上（python server 未運行）→ 無法得知 → false
        if ($response_body === false) {
            return false;
        }
        
        $response_data = json_decode($response_body, true);
        
        // 判定：回應不可解析 → false
        if (!is_array($response_data)) {
            return false;
        }
        
        return (bool)($response_data["daemon_alive"] ?? false);
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

    // curl 檢查：HTTP GET health（connect 逾時＋總逾時；判定＝2xx ＋ json status == "ok"）
    private function check_server_alive_by_curl($server_host, $server_port, $server_health_path, $server_check_timeout_ms, $server_check_total_timeout_sec)
    {
        $health_url = "http://" . $server_host . ":" . $server_port . $server_health_path;
        
        $curl_handle = curl_init($health_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT_MS, $server_check_timeout_ms);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, $server_check_total_timeout_sec);
        $response_body = curl_exec($curl_handle);
        $http_code     = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
        curl_close($curl_handle);
        
        // 判定：傳輸失敗（server 未啟動／連不上）→ 未運行
        if ($response_body === false) {
            return false;
        }
        
        // 判定：非 2xx → 未運行（有回應但唔正常）
        if ($http_code < 200 || $http_code >= 300) {
            return false;
        }
        
        $response_data = json_decode($response_body, true);
        
        // 判定：回應不可解析 → 未運行
        if (!is_array($response_data)) {
            return false;
        }
        
        // 判定：status 非 ok → 未運行
        if (($response_data["status"] ?? "") !== "ok") {
            return false;
        }
        
        return true;
    }
    
    // tcp 檢查：只驗 port（fsockopen connect 得上即生——唔讀內容）
    private function check_server_alive_by_tcp($server_host, $server_port, $server_check_timeout_ms)
    {
        $connect_timeout_sec  = $server_check_timeout_ms / 1000;
        $socket_error_number  = 0;
        $socket_error_string  = "";
        
        $socket_handle = @fsockopen($server_host, $server_port, $socket_error_number, $socket_error_string, $connect_timeout_sec);
        
        // 判定：連唔上 → 未運行
        if ($socket_handle === false) {
            return false;
        }
        
        fclose($socket_handle);
        
        return true;
    }
    
    // 目前設定的 server port（runtime 動態層——未存時為靜態預設）
    private function get_server_port()
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        
        return (int)$server_settings["port"];
    }
    
    // 讀 bridge 自報身份（/health——storage_dir 用嚟判別邊個實例；讀唔到＝空陣列）
    private function read_bridge_health($server_host, $server_port)
    {
        $health_data = [];

        $health_url = "http://" . $server_host . ":" . $server_port . "/health";

        $curl_handle = curl_init($health_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 3);

        $response_body = curl_exec($curl_handle);
        curl_close($curl_handle);

        // 判定：無回應 → 空
        if ($response_body === false) {
            return $health_data;
        }

        $decoded_body = json_decode($response_body, true);

        // 判定：解析到陣列 → 用
        if (is_array($decoded_body)) {
            $health_data = $decoded_body;
        }

        return $health_data;
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
