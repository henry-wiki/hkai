<?php

namespace sc\app\hkai\plugins\sc_stream\sse;

// == sse_Class：SSE 共用輸出（singleton——經 sc_stream()->sse() 取得） ==
// == 職責：headers／清緩衝／逐塊 flush／首幀 handshake／query 參數解析 ==
// == 上游：sse_state_Class、sse_prompt_Class；下游：無（純輸出工具） ==

class sse_Class
{
    // 首幀 handshake 塞滿量（bytes——沖走下層 4KB 緩衝）
    private const HANDSHAKE_PAD_BYTES = 4096;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    // SSE headers（唔經框架 response——串流必須直接輸出）
    public function send_headers()
    {
        header("Content-Type: text/event-stream; charset=utf-8");
        header("Cache-Control: no-cache, no-transform");
        header("Connection: keep-alive");
        header("X-Accel-Buffering: no");
    }

    // 清空全部輸出緩衝（框架可能已開緩衝）
    public function flush_all_output_buffers()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    // 推送後即時 flush（逐塊送出）
    public function flush_output()
    {
        // 判定：仍有緩衝 → 先送
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

    // 首幀 handshake（EventSource onopen——塞滿下層緩衝）
    public function send_handshake()
    {
        // 連接器位址（handshake 一併帶——前端可據此換埠重開 presence）
        $runtime_configs = hkai()->runtime()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];

        $bridge_base_url = "http://" . ($server_settings["host"] ?? "") . ":" . ($server_settings["port"] ?? "");

        $handshake_payload            = [];
        $handshake_payload["ok"]      = true;
        $handshake_payload["padding"] = str_repeat("-", self::HANDSHAKE_PAD_BYTES);
        $handshake_payload["base_url"] = $bridge_base_url;

        echo "event: open\n";
        echo "data: " . json_encode($handshake_payload, JSON_UNESCAPED_SLASHES) . "\n\n";

$this->flush_output();
    }

    // 開串流（headers＋清緩衝＋handshake——兩條線共用）
    public function open_stream()
    {
        sc_stream()->sse()->send_headers();
        sc_stream()->sse()->flush_all_output_buffers();
        sc_stream()->sse()->send_handshake();
    }

    // 推一個 event（event 名＋payload——兩條線共用）
    public function send_event($event_name, $event_payload)
    {
        $event_json = json_encode($event_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 判定：payload 編碼失敗（非 UTF-8 等）→ 送空物件，唔好斷幀
        if ($event_json === false) {
            $event_json = "{}";
        }

        $event_name = (string)$event_name;

        // 判定：event 名含換行／怪字元（防注入第二行）→ 退回 message
        if (preg_match("/^[A-Za-z0-9_-]+$/", $event_name) !== 1) {
            $event_name = "message";
        }

        echo "event: " . $event_name . "\n";
        echo "data: " . $event_json . "\n\n";

        sc_stream()->sse()->flush_output();
    }

    // 保活 ping（comment 幀——單行註釋，唔帶 data）
    public function send_ping()
    {
        echo ": ping\n\n";

        sc_stream()->sse()->flush_output();
    }

    // 收線（event: close——通知前端本輪已完）
    public function send_close()
    {
        echo "event: close\n";
        echo "data: {}\n\n";

        sc_stream()->sse()->flush_output();
    }
}
