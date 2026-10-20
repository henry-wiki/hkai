<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\action;

// == sc_ai_settings server handle_settings_auto_assign_port：server 域動作（trait——掛入 server_Class；檔＝動作）
// == 職責：喺 6001–6999 掃一個未監聽嘅 port（netstat LISTENING 一次過）→ 回 {ok, port}

trait handle_settings_auto_assign_port
{
    // 自動分配 port（POST——範圍內第一個可用埠；全段被佔＝409）
    public function handle_settings_auto_assign_port()
    {
        $port_range_start = 6001;
        $port_range_end   = 6999;

        // 佔用清單（一次 netstat——唔逐埠探測）
        $listening_ports = $this->collect_listening_ports();

        $available_port = 0;

        // 逐個候選埠（範圍內第一個未被監聽）
        for ($port_candidate = $port_range_start; $port_candidate <= $port_range_end; $port_candidate++) {
            // 判定：已被監聽 → 下一個
            if (isset($listening_ports[$port_candidate])) {
                continue;
            }

            $available_port = $port_candidate;
            break;
        }

        // 判定：全段被佔用 → 409
        if ($available_port === 0) {
            return sc_response()->json(["ok" => false, "error" => hkai__i18n()->get("settings.server.error_no_available_port")], 409);
        }

        return sc_response()->json(["ok" => true, "port" => $available_port]);
    }

    // 讀監聽中嘅 TCP 埠（netstat -ano -p tcp；回 [port => pid]）
    private function collect_listening_ports()
    {
        $listening_ports = [];

        $netstat_lines = [];
        @exec("netstat -ano -p tcp", $netstat_lines);

        // 逐行解析（LISTENING 行——本地位址尾段＝port）
        foreach ($netstat_lines as $netstat_line) {
            $netstat_columns = preg_split('/\s+/', trim($netstat_line));

            // 判定：欄位不足（表頭等）→ 跳
            if (count($netstat_columns) < 5) {
                continue;
            }

            // 判定：非 LISTENING → 跳
            if ($netstat_columns[3] !== "LISTENING") {
                continue;
            }

            $local_address = $netstat_columns[1];
            $address_parts = explode(":", $local_address);
            $port_text     = end($address_parts);

            // 判定：尾段非數字（IPv6 變體等）→ 跳
            if (!ctype_digit($port_text)) {
                continue;
            }

            $listening_ports[(int)$port_text] = (int)$netstat_columns[4];
        }

        return $listening_ports;
    }
}
