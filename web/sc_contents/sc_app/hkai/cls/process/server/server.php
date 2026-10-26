<?php

namespace sc\app\hkai\cls\process\server;

// == server_Class：python server 生命週期（singleton——經 hkai()->process()->server() 取得）
// == 職責：狀態（get_server_status——讀心跳＋pid 核實）／啟動（交 server_host 取位址 → 寫設定 → 拉起 → 等心跳 → 同步指示檔）／停止（交 bridge 自殺 → 等心跳檔消失 → 逾時交清道夫）／重啟（停→起）
// == 回傳：outcome 表 ["ok" => bool, ...]；失敗帶 error_kind（no_port／failed）——HTTP status 由 request 層決定
// == 上游：request/process/bridge/{start,stop,restart}_server.php、health 輪詢、server_status 端點、prompt_send 前置檢查
// == 下游：server_host（位址／白名單）、bridge_watch（等心跳）、launcher（spawn／cleanup）、instruction（指示檔）

class server_Class
{
    // 停止確認上限（秒——等心跳檔消失；自殺 timer 3 秒＋裕度）
    private const STOP_WAIT_SEC = 5;

    // 啟動確認上限（秒——等心跳檔出現；Python 冷啟約 2–3 秒＋裕度）
    private const START_WAIT_SEC = 15;

    // 交 bridge 停止嘅 HTTP 回應上限（秒——bridge 殺完子孫即回，呢個只係保險上限）
    private const STOP_REQUEST_TIMEOUT_SEC = 10;

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

    // 啟動（已起檢查 → 位址解析 → 寫設定 → 拉起 → 等心跳 → 確保環境檔）
    public function start_server($server_host = "", $server_port = "")
    {
        $logger = hkai()->runtime()->logger();

        $logger->info("start_server: begin (requested host=" . $server_host . ", port=" . $server_port . ")");

        $server_status_result = $this->get_server_status();

        // 判定：bridge 已啟動（心跳新鮮＋pid 在生）→ failed（要重啟請用 restart；唔使再揀埠）
        if ($server_status_result["is_alive"]) {
            $logger->warn("start_server: already running — skip");

            return ["ok" => false, "error_kind" => "failed", "error" => "伺服器已經啟動"];
        }

        $address_result = $this->host()->resolve_server_address($server_host, $server_port);

        // 判定：揀唔到位址／埠 → no_port
        if ($address_result === false) {
            $logger->error("start_server: no available port");

            return ["ok" => false, "error_kind" => "no_port", "error" => hkai()->i18n()->get("settings.server.error_no_available_port")];
        }

        $server_host = $address_result["host"];
        $server_port = $address_result["port"];

        $logger->info("start_server: resolved " . $server_host . ":" . $server_port);

        // 記錄本次來源 origin（白名單——SSE CORS；去重附加）
        $this->host()->append_origin_whitelist();

        // 寫入本次要起嘅 host／port（python 唯一設定來源——拉起前先寫，bridge 開機即讀）
        $server_info = [];
        $server_info["host"] = $server_host;
        $server_info["port"] = $server_port;
        hkai()->runtime()->write_runtime_configs("server_settings", $server_info);

        $spawn_time = time();

        // 拉起 bridge（標準 launcher——關鍵字＋旗標；--bg＝非阻塞；runtime 路徑＝機器層值）
        // bridge 自理日誌：data/logs/bridge.error.log／bridge.audit.log
        $launcher_result = hkai()->process()->launcher()->exec([
            "bridge",
            "--bg",
        ]);

        // 判定：launcher 送出失敗
        if ($launcher_result["ok"] !== true) {
            $logger->error("start_server: launcher failed — " . ($launcher_result["error"] ?? "unknown"));

            return ["ok" => false, "error_kind" => "failed", "error" => "server command failed"];
        }

        // 等待 bridge 心跳出現（交 bridge_watch 監視；心跳＝生存證明）
        $is_bridge_started = hkai()->process()->bridge_watch()->wait_heartbeat_started(self::START_WAIT_SEC, $spawn_time);

        // 判定：逾時冇新心跳 → failed
        if ($is_bridge_started === false) {
            $logger->error("start_server: heartbeat timeout (15s) — " . $server_host . ":" . $server_port);

            return ["ok" => false, "error_kind" => "failed", "error" => "啟動失敗：未收到 server 就緒回覆。"];
        }

        // 系統指示檔：prompt 每次覆寫、env 由 bridge JSON 重建（失敗各自記 log，唔影響啟動）
        hkai()->process()->instruction()->sync_system_instructions();

        // 回報實際位址（runtime.json server_settings——前端更新 stream base_url 用）
        $runtime_configs = hkai()->runtime()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];

        $actual_host = (string)($server_settings["host"] ?? "");
        $actual_port = (int)($server_settings["port"] ?? 0);

        $logger->info("start_server: ready " . $actual_host . ":" . $actual_port);

        return ["ok" => true, "host" => $actual_host, "port" => $actual_port];
    }

    // 停止（交 bridge 殺子孫＋自殺 → 等心跳檔消失；逾時交清道夫）
    public function stop_server()
    {
        $logger           = hkai()->runtime()->logger();
        $bridge_heartbeat = hkai()->process()->read_bridge_heartbeat();
        $bridge_pid       = (int)($bridge_heartbeat["pid"] ?? 0);

        // 判定：bridge 已死（冇心跳 pid／程序唔在生）→ 交清道夫收尾
        if ($bridge_pid < 1 || hkai()->process()->is_process_alive($bridge_pid) === false) {
            $logger->info("stop_server: bridge not alive (pid=" . $bridge_pid . ") — run cleanup");

            $cleanup_result = hkai()->process()->launcher()->exec(["cleanup"]);

            // 判定：清道夫失敗 → 記 log（唔影響「已停」回應）
            if ($cleanup_result["ok"] !== true) {
                $logger->warn("stop_server: cleanup failed (exit=" . $cleanup_result["exit_code"] . ")");
            }

            return ["ok" => true, "pid" => $bridge_pid, "forced" => false, "waited_sec" => 0];
        }

        $logger->info("stop_server: begin (pid=" . $bridge_pid . ")");

        // 交 bridge：殺全部子孫（唔包自己）→ 回 JSON → 3 秒後自殺
        $stop_request = hkai()->process()->bridge()->request("/process/stop/", [], self::STOP_REQUEST_TIMEOUT_SEC);

        // 判定：bridge 回應帶自己 pid → 以佢為準（最新 running pid）
        if (isset($stop_request["pid"])) {
            $bridge_pid = (int)$stop_request["pid"];
        }

        // 判定：bridge 拒絕／無回應 → 記 log（照樣等心跳檔消失）
        if (($stop_request["ok"] ?? false) !== true) {
            $logger->warn("stop_server: bridge stop request failed — wait heartbeat removal");
        }

        $stop_started_at = microtime(true);

        // 等 bridge 刪自己心跳檔（3 秒內消失＝正常結束）
        $is_heartbeat_removed = hkai()->process()->bridge_watch()->wait_heartbeat_removed($bridge_pid, self::STOP_WAIT_SEC);

        // 判定：逾時心跳仍在 → 寫 log → 交清道夫強制終止
        if ($is_heartbeat_removed === false) {
            $logger->info("stop_server: heartbeat still exists after " . self::STOP_WAIT_SEC . "s — run cleanup");

            $cleanup_result = hkai()->process()->launcher()->exec(["cleanup"]);

            // 判定：清道夫失敗 → 記 log（下面照樣睇心跳檔判結果）
            if ($cleanup_result["ok"] !== true) {
                $logger->warn("stop_server: cleanup failed (exit=" . $cleanup_result["exit_code"] . ")");
            }

            $is_heartbeat_removed = hkai()->process()->bridge_watch()->wait_heartbeat_removed($bridge_pid, self::STOP_WAIT_SEC);
        }

        $waited_sec = (int)round(microtime(true) - $stop_started_at);

        // 判定：心跳檔仍在 → 失敗
        if ($is_heartbeat_removed === false) {
            $logger->error("stop_server: heartbeat still exists (pid=" . $bridge_pid . ")");

            return ["ok" => false, "error_kind" => "failed", "error" => "server still alive (pid " . $bridge_pid . ")"];
        }

        $logger->info("stop_server: done (pid=" . $bridge_pid . ", waited_sec=" . $waited_sec . ")");

        return ["ok" => true, "pid" => $bridge_pid, "waited_sec" => $waited_sec];
    }

    // 重啟＝先停（bridge 殺子孫＋自殺、等心跳檔消失）→ 再起（地址由 start_server 自己揀）
    public function restart_server()
    {
        $this->stop_server();

        return $this->start_server();
    }

    // 伺服器狀態（讀統一心跳檔＋pid 現查；殘檔即清——起 server／開 server tab／送 prompt 前用）
    public function get_server_status()
    {
        // 心跳來源：統一心跳（process_status/bridge.<pid>.json——每程序每秒自我覆寫）
        $heartbeat_data = hkai()->process()->read_bridge_heartbeat();

        // 判定：無心跳 → 空（未啟動）
        if ($heartbeat_data === null) {
            $heartbeat_data = [];
        }

        $server_status_result = [];
        $server_status_result["ok"]            = true;
        $server_status_result["checked_at"]    = date("c");
        $server_status_result["is_alive"]      = (count($heartbeat_data) > 0);
        $server_status_result["server_status"] = (count($heartbeat_data) > 0) ? "running" : "stopped";
        $server_status_result["heartbeat"]     = $heartbeat_data;

        // 判定：冇心跳 → stopped（唔使查 pid）
        if ($server_status_result["is_alive"] === false) {
            return $server_status_result;
        }

        $bridge_pid = (int)($heartbeat_data["pid"] ?? 0);

        // 判定：pid 現查在生 → 通過
        if (hkai()->process()->is_process_alive($bridge_pid)) {
            return $server_status_result;
        }

        // 心跳在但 pid 已死（殘檔）→ 只報 stopped（清檔係清道夫 launcher cleanup 嘅工作）
        $server_status_result["is_alive"]      = false;
        $server_status_result["server_status"] = "stopped";
        $server_status_result["heartbeat"]     = [];

        return $server_status_result;
    }

    // 位址工具域（host 解析／port 抽選／origin 白名單）
    private function host()
    {
        return server_host_Class::getInstance();
    }
}
