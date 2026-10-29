<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\conn;

// == conn fetch_entries：伺服器連線狀態（trait——掛入 conn_Class） ==
// == 上游：conn_Class（經 monitor()->fetch_target_entries()）；下游：process server 心跳快查（讀本機檔，零 HTTP） ==

trait fetch_entries
{
    // 取當刻 entries：{bridge: {is_alive}}
    public function fetch_entries($monitor_context = [])
    {
        $conn_map = [];

        $server_status = hkai()->process()->server()->get_server_status();

        $runtime_configs = hkai()->runtime()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];

        $conn_entry             = [];
        $conn_entry["is_alive"] = ($server_status["is_alive"] === true);
        $conn_entry["base_url"] = "http://" . ($server_settings["host"] ?? "") . ":" . ($server_settings["port"] ?? "");

        $conn_map["bridge"] = $conn_entry;

        return $conn_map;
    }
}
