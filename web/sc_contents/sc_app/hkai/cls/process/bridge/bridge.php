<?php

namespace sc\app\hkai\cls\process\bridge;

// == bridge_Class：bridge 通訊 client（singleton——經 hkai()->process()->bridge() 取得） ==
// == 職責：request（對 bridge 發 HTTP JSON）＋ post_fire_and_forget（單向通知——唔等回應）
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
        
        // 標準 HTTP（sc_curl——自動 UA；連接上限 300ms、總時限由呼叫端定）
        $curl_result = sc_curl($request_url, [
            "method"             => "POST",
            "json"               => true,
            "post"               => $data,
            "connect_timeout_ms" => 300,
            "timeout"            => $timeout_sec,
        ]);

        // 判定：超時（時限到——操作可能仲喺橋嗰邊跑緊，唔等於橋壞）→ 失敗（帶時限秒數）
        if ($curl_result["errno"] === CURLE_OPERATION_TIMEDOUT) {
            return ["ok" => false, "error" => "bridge request timed out (" . $timeout_sec . "s)"];
        }

        // 判定：其他傳輸錯誤（bridge 未運行）→ 失敗
        if ($curl_result["errno"] !== 0) {
            return ["ok" => false, "error" => "python bridge not running"];
        }

        // 判定：HTTP 非 200（路由不存在／bridge 未更新／內部錯誤）→ 寫 log、回失敗
        if ($curl_result["status"] !== 200) {
            hkai()->runtime()->logger()->warn("bridge request " . $path . " http " . $curl_result["status"]);

            return ["ok" => false, "error" => "bridge request failed"];
        }

        $decoded = $curl_result["json"];

        // 判定：回應不可解析 → 失敗
        if (!is_array($decoded)) {
            return ["ok" => false, "error" => "invalid bridge output"];
        }

        return $decoded;
    }

    // 單程通知（POST /path——curl 送出即返；回應唔讀；上限 300ms；失敗交重入 resume 補派）
    public function post_fire_and_forget($path, $data = [], $timeout_ms = 300)
    {
        $runtime_configs = hkai()->runtime()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];

        $server_host = (string)($server_settings["host"] ?? "127.0.0.1");
        $server_port = (int)($server_settings["port"] ?? 0);
        $request_url = "http://" . $server_host . ":" . $server_port . "/" . ltrim($path, "/");

        // 標準 HTTP（sc_curl——自動 UA；連接上限 300ms、整體上限由呼叫端定）
        $curl_result = sc_curl($request_url, [
            "method"             => "POST",
            "json"               => true,
            "post"               => $data,
            "connect_timeout_ms" => 300,
            "timeout_ms"         => $timeout_ms,
        ]);

        // 判定：連唔上（bridge 未運行；超時除外）→ false（唔等、唔重試——重入時 resume 會補派）
        if ($curl_result["errno"] !== 0 && $curl_result["errno"] !== CURLE_OPERATION_TIMEDOUT) {
            hkai()->runtime()->logger()->warn("bridge fire_and_forget failed: " . $path . " curl_errno=" . $curl_result["errno"]);

            return false;
        }

        return true;
    }

}
