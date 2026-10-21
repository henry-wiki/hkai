<?php

namespace sc\app\hkai\cls\sc_python_bridge;

// == sc_python_bridge_daemon_Class：aider_daemon 通知入口（singleton——經 bridging hkai__python_bridge_daemon() 取得） ==
// == 職責：notify_daemon_add_message（POST /daemon/add_message/——喚醒與否由 bridge 決定；PHP 不檢查、不喚醒） ==
// == 設定：直接從 hkai_core 讀取（hkai__core()->get_runtime_configs()["server_settings"]） ==

class sc_python_bridge_daemon_Class
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

    // 通知 bridge 有新訊息（POST /daemon/add_message/——喚醒與否由 bridge 決定；PHP 不檢查、不喚醒）
    // 回傳 true = bridge 已收到（HTTP 200）；false = bridge 未運行／路由不存在／HTTP 錯誤（訊息仍已落地）
    public function notify_daemon_add_message($chat_id, $message_id)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        
        $server_host = (string)($server_settings["host"] ?? "127.0.0.1");
        $server_port = (int)($server_settings["port"] ?? 0);
        $add_message_url = "http://" . $server_host . ":" . $server_port . "/daemon/add_message/";
        
        $payload = [];
        $payload["chat_id"]    = (int)$chat_id;
        $payload["message_id"] = (int)$message_id;
        
        $curl_handle = curl_init($add_message_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT_MS, 300);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 5);
        $response_body = curl_exec($curl_handle);
        $http_code     = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
        curl_close($curl_handle);
        
        // 判定：連唔上（bridge 未運行）→ false
        if ($response_body === false) {
            return false;
        }
        
        // 判定：HTTP 非 200（路由不存在／bridge 未更新／內部錯誤）→ 寫 log、回 false（不可靜默當成功）
        if ($http_code !== 200) {
            hkai__sc_runtime_io()->warn("aider_daemon add_message http " . $http_code . " (bridge may be outdated)");
            return false;
        }
        
        return true;
    }
}
