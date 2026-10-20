<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\view;

// == sc_ai_settings server handle_fetch_server_log：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-10-05：Live logs 輪詢——帶 offset 標記：全量（初次／輪替）或增量（只回新行）；前端 replace／prepend ==

trait handle_fetch_server_log
{
    // GET fetch_server_log handler（帶 offset＝只回新行；0／無效＝全量。keep_auto_refresh 續鏈旗標在回應根層）
    public function handle_fetch_server_log()
    {
        $request_data = sc_request()->json();

        $log_byte_offset = (int)($request_data["offset"] ?? 0);

        // 讀日誌（0／無效 offset＝全量；>0＝增量——只回完整行）
        $server_log_read = hkai__python_bridge()->read_server_log_after_offset($log_byte_offset, 500);

        $server_log_lines_html = $this->render_server_log_lines($server_log_read["lines"]);

        // 判定：增量 → 只回新行（前端 prepend——新行喺頂）
        if ($server_log_read["mode"] === "append") {
            $response_data = [];
            $response_data["ok"]                = true;
            $response_data["keep_auto_refresh"] = true;
            $response_data["log_mode"]          = "append";
            $response_data["log_offset"]        = $server_log_read["offset"];
            $response_data["log_lines_html"]    = $server_log_lines_html;

            return sc_response()->json($response_data);
        }

        // 全量容器（帶新 offset 標記＋data-slot——前端整隻換）
        $log_container_html = "<div class='settings_server_log' data-slot='server_log' data-log_offset='" . $server_log_read["offset"] . "'>" . $server_log_lines_html . "</div>";

        $response_data = [];
        $response_data["ok"]                = true;
        $response_data["keep_auto_refresh"] = true;
        $response_data["log_mode"]          = "full";
        $response_data["log_offset"]        = $server_log_read["offset"];
        $response_data["log_html"]          = $log_container_html;

        return sc_response()->json($response_data);
    }
}
