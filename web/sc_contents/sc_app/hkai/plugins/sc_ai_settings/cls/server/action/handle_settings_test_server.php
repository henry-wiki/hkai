<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\action;

// == sc_ai_settings server handle_settings_test_server：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat server_Class 分遷入（one file one action） ==

trait handle_settings_test_server
{
    // POST server 連線測試 handler（表單 host／port——探測後寫入 runtime.json，host／port 立即生效）
    public function handle_settings_test_server()
    {
        $request_data = sc_request()->json();

        $server_host     = trim($request_data["host"] ?? "");
        $server_port     = trim((string)($request_data["port"] ?? ""));
        $server_base_url = "http://" . $server_host . ":" . $server_port;

        // 驗證（host／port——不合法不探測、不寫 runtime）
        $errors = $this->validate_host_port($server_host, $server_port);

        // 判定：有錯誤 → 回 error 狀態（訊息取第一條——不寫 runtime）
        if (!empty($errors)) {
            $first_error = reset($errors);
            return sc_response()->json(["state" => "error", "message" => $first_error]);
        }

        $test_result = $this->get_host_port_test_result($server_base_url);

        // 寫入 runtime.json（server_settings 覆蓋層——host／port ＋ testing_result）
        $server_settings_update = [];
        $server_settings_update["host"]           = $server_host;
        $server_settings_update["port"]           = (int)$server_port;
        $server_settings_update["testing_result"] = $test_result;
        hkai__core()->write_runtime_configs("server_settings", $server_settings_update);

        return sc_response()->json($test_result);
    }
}
