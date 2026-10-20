<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\view;

// == sc_ai_settings server handle_fetch_server_settings：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat server_Class 分遷入（one file one action） ==

trait handle_fetch_server_settings
{
    // GET fetch_server_settings handler（讀 runtime.json 動態層——未存時由靜態預設為底；按鈕與狀態文字依實測）
    public function handle_fetch_server_settings()
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];

        // 已知狀態（runtime 已存的 testing_result——未測試＝空；測試與否由獨立程序發起）
        $testing_result = $server_settings["testing_result"] ?? [];
        $known_state    = $testing_result["state"] ?? "";

        // 實測狀態（完整檢查為準——檔＋pid 二重核實；開 server tab 即校正）
        $server_status = "stopped";

        $bridge_check = hkai__python_bridge_check()->check_is_python_bridge_running_full();

        // 判定：完整檢查報 alive → running
        if ($bridge_check["is_alive"]) {
            $server_status = "running";
        }

        $html = $this->get_modal_content($server_settings, $known_state, $server_status);

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
