<?php

namespace sc\app\hkai\cls\sc_python_bridge;

// == sc_python_bridge_check_Class：bridge 狀態檢查（singleton——經 bridging hkai__python_bridge_check() 取得） ==
// == 快查（check_is_python_bridge_running）：讀統一心跳檔 process_status/bridge.*.json——存在＋updated_at ≤60s ＝ running ==
// == 全查（check_is_python_bridge_running_full）：快查＋用檔案 pid 現查 process 在生（二重核實——開 server tab 用） ==
// == 結果統一格式：is_alive／checked_at／server_status／heartbeat 原始資料 ==

class sc_python_bridge_check_Class
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

    // bridge 快查（讀統一心跳檔——存在＋新鮮＝running；統一結果：is_alive／checked_at／server_status／heartbeat）
    public function check_is_python_bridge_running()
    {
        // 心跳來源：統一心跳（process_status/bridge.<pid>.json——每程序每秒自我覆寫）
        $heartbeat_data = hkai__python_bridge_process()->read_bridge_heartbeat();

        // 判定：無心跳 → 空（未啟動）
        if ($heartbeat_data === null) {
            $heartbeat_data = [];
        }

        $bridge_result = [];
        $bridge_result["ok"]            = true;
        $bridge_result["checked_at"]    = date("c");
        $bridge_result["is_alive"]      = (count($heartbeat_data) > 0);
        $bridge_result["server_status"] = (count($heartbeat_data) > 0) ? "running" : "stopped";
        $bridge_result["heartbeat"]     = $heartbeat_data;

        return $bridge_result;
    }

    // 完整檢查（二重核實——快查＋pid 現查；開 server tab／按啟用前用，寧緊唔鬆）
    public function check_is_python_bridge_running_full()
    {
        $bridge_check = $this->check_is_python_bridge_running();

        // 判定：快查已報唔活 → 直接回
        if (!$bridge_check["is_alive"]) {
            return $bridge_check;
        }

        $bridge_record = $bridge_check["heartbeat"];
        $bridge_pid    = (int)($bridge_record["pid"] ?? 0);

        // 判定：pid 現查在生 → 二重通過
        if (hkai__core()->is_process_alive($bridge_pid)) {
            return $bridge_check;
        }

        // pid 已死（心跳未過期嘅殘檔）→ 清檔、回 stopped
        $process_status_dir = hkai__core()->get_process_status_dir();
        @unlink($process_status_dir . "/bridge." . $bridge_pid . ".json");

        $bridge_check["is_alive"]      = false;
        $bridge_check["server_status"] = "stopped";
        $bridge_check["heartbeat"]     = [];

        return $bridge_check;
    }


    // 伺服器啟動驗證（供送出前置檢查——無法連接＝E_SERVER_DOWN 統一錯誤結構）
    public function validate_server_running()
    {
        $bridge_check = $this->check_is_python_bridge_running();

        // 判定：bridge 存活（統一心跳即時判定）→ 通過
        $is_alive = isset($bridge_check["is_alive"]) ? $bridge_check["is_alive"] : false;
        if ($is_alive === true) {
            return ["ok" => true];
        }

        return [
            "ok"      => false,
            "code"    => "E_SERVER_DOWN",
            "title"   => hkai__i18n()->get("error.E_SERVER_DOWN.title"),
            "message" => "無法連接本機伺服器，訊息未送出。",
            "hint"    => "請先啟動伺服器（Settings → Server →「啟用」）再重新發送。",
        ];
    }

}
