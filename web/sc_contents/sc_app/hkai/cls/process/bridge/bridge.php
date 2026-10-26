<?php

namespace sc\app\hkai\cls\process\bridge;

// == bridge_Class：bridge 通訊 client（singleton——經 hkai()->process()->bridge() 取得） ==
// == 職責：request（對 bridge 發 HTTP JSON）＋ notify_server_new_prompt（POST /daemon/add_message/——喚醒與否由 bridge 決定）
// == 上游：所有要問 bridge 嘅 PHP（程序掃描／prompt 派發／health）；下游：bridge HTTP 端點
// == 設定：server_settings（host／port）由 hkai()->runtime() 讀

class bridge_Class
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

    // 通用 bridge 請求（POST /path——data 為 payload；$timeout_sec＝整個請求總時限（默認 30 秒——慢操作由呼叫端加大）；PHP 只負責傳參與讀 JSON）
    public function request($path, $data = [], $timeout_sec = 30)
    {
        $runtime_configs = hkai()->runtime()->get_runtime_configs();
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
            hkai()->runtime()->logger()->warn("bridge request " . $path . " http " . $http_code);
            return ["ok" => false, "error" => "bridge request failed"];
        }
        
        $decoded = json_decode($response_body, true);
        
        // 判定：回應不可解析 → 失敗
        if (!is_array($decoded)) {
            return ["ok" => false, "error" => "invalid bridge output"];
        }
        
        return $decoded;
    }

    // 通知 bridge 有新訊息（POST /daemon/add_message/——喚醒與否由 bridge 決定；PHP 不檢查、不喚醒）
    // 回傳 true = bridge 已收到（HTTP 200）；false = bridge 未運行／路由不存在／HTTP 錯誤（訊息仍已落地）
    public function notify_server_new_prompt($chat_id, $message_id, $agent = "")
    {
        $runtime_configs = hkai()->runtime()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        
        $server_host = (string)($server_settings["host"] ?? "127.0.0.1");
        $server_port = (int)($server_settings["port"] ?? 0);
        $add_message_url = "http://" . $server_host . ":" . $server_port . "/daemon/add_message/";
        
        $payload = [];
        $payload["chat_id"]    = (int)$chat_id;
        $payload["message_id"] = (int)$message_id;
        $payload["agent"]      = (string)$agent;
        
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
            hkai()->runtime()->logger()->warn("aider_daemon add_message http " . $http_code . " (bridge may be outdated)");
            return false;
        }
        
        return true;
    }
}
