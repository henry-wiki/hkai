<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\action;

// == sc_ai_settings server handle_settings_start_server：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat server_Class 分遷入（one file one action） ==

trait handle_settings_start_server
{
    // POST 啟動 python server handler（先驗表單 host／port；生命週期處理在 sc_python_bridge——此處只轉呼）
    public function handle_settings_start_server()
    {
        $request_data = sc_request()->json();

        $server_host = trim($request_data["host"] ?? "");
        $server_port = trim((string)($request_data["port"] ?? ""));

        // 自動模式：未指定 host → 本機
        if ($server_host === "") {
            $server_host = "127.0.0.1";
        }

        // 自動模式：未指定 port → 自動分配一個可用埠
        if ($server_port === "") {
            $available_port = hkai__python_bridge()->find_available_server_port();

            // 判定：全段被佔用 → 409
            if ($available_port === 0) {
                return sc_response()->json(["ok" => false, "error" => hkai__i18n()->get("settings.server.error_no_available_port")], 409);
            }

            $server_port = (string)$available_port;
        }

        // 驗證（host／port 共用驗證——同存檔一致）
        $errors = $this->validate_host_port($server_host, $server_port);

        // 判定：驗證失敗 → 400＋合併訊息（前端 alert 顯示）
        if (!empty($errors)) {
            return sc_response()->json(["ok" => false, "error" => implode("\n", $errors)], 400);
        }

        // 交生命週期處理（帶表單現值——佔用檢查＋寫入拉起）
        $start_result = hkai__python_bridge()->start_server($server_host, (int)$server_port);

        // 判定：啟動失敗（port 被佔／bridge 自報 failed／逾時——含訊息）→ 500
        if (!$start_result["ok"]) {
            return sc_response()->json(["ok" => false, "error" => $start_result["message"]], 500);
        }

        // 系統級環境檔：啟動成功即確保存在（缺檔→向 bridge 索要後寫入；存在→跳過零成本）
        sc_ai_diagnostics()->system_env()->write_system_env_file(false);

        // 回傳實際連接埠（前端更新 stream base_url 用）
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        $actual_host     = (string)($server_settings["host"] ?? "");
        $actual_port     = (int)($server_settings["port"] ?? 0);

        return sc_response()->json(["ok" => true, "host" => $actual_host, "port" => $actual_port]);
    }
}
