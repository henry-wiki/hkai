<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\action;

// == sc_ai_settings server handle_settings_stop_server：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat server_Class 分遷入（one file one action） ==

trait handle_settings_stop_server
{
    // POST 停止 python server handler（生命週期處理在 sc_python_bridge——此處只轉呼）
    public function handle_settings_stop_server()
    {
        $stopped = hkai__python_bridge()->stop_server();

        // 判定：找不到 listener → server 未運行（400）
        if (!$stopped) {
            return sc_response()->json(["ok" => false, "error" => "server not running"], 400);
        }

        return sc_response()->json(["ok" => true]);
    }
}
