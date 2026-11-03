<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\conn;

// == conn fetch_source_entries：取此 target 即時資料（trait——掛入 conn_Class） ==
// == 上游：本檔 init_snapshot()／monitor()->get_target_source_entries() ==
// == 下游：即時資料來源（DB／檔案）——唔係讀 snapshot.json ==

trait fetch_source_entries
{
    // 取當刻 entries：{bridge: {is_alive}}
    public function fetch_source_entries()
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
