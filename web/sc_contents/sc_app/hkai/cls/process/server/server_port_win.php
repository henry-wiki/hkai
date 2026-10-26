<?php

namespace sc\app\hkai\cls\process\server;

// == server_port_win_Class：Windows 監聽埠清單（singleton——由 server_port()->port_platform() 選用）
// == 職責：netstat -ano -p tcp 一次 → 解析 LISTENING 行 → 回 [port => pid]
// == 上游：server_port_Class；下游：Windows netstat

class server_port_win_Class
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

    // 讀監聽中嘅 TCP 埠（netstat -ano -p tcp；回 [port => pid]）
    public function get_listening_ports()
    {
        $command_output_lines = [];
        @exec("netstat -ano -p tcp", $command_output_lines);

        return $this->parse_listening_ports($command_output_lines);
    }

    // 解析 netstat 輸出（LISTENING 行；本地位址尾段＝port）
    private function parse_listening_ports($command_output_lines)
    {
        $listening_ports = [];

        // 逐行解析（LISTENING 行；本地位址尾段＝port）
        foreach ($command_output_lines as $command_output_line_item) {
            $command_output_columns = preg_split('/\s+/', trim($command_output_line_item));

            // 判定：欄位不足（表頭等）→ 跳
            if (count($command_output_columns) < 5) {
                continue;
            }

            // 判定：非 LISTENING → 跳
            if ($command_output_columns[3] !== "LISTENING") {
                continue;
            }

            $local_port = $this->get_port_from_colon_address($command_output_columns[1]);

            // 判定：解析唔到 port → 跳
            if ($local_port === 0) {
                continue;
            }

            $listening_ports[$local_port] = (int)$command_output_columns[4];
        }

        return $listening_ports;
    }

    // 由「ip:port」取 port（含 [::1]:6002 形式）；0＝解析唔到
    private function get_port_from_colon_address($local_address)
    {
        $address_parts = explode(":", $local_address);
        $port_text     = end($address_parts);

        // 判定：尾段非數字 → 0
        if (!ctype_digit($port_text)) {
            return 0;
        }

        return (int)$port_text;
    }
}
