<?php

namespace sc\app\hkai\cls\process\server;

// == server_port_nix_Class：Linux（unix）監聽埠清單（singleton——由 server_port()->port_platform() 選用）
// == 職責：ss -ltnp 一次 → 解析 LISTEN 行 → 回 [port => pid]（無權限睇嘅程序 pid＝0）
// == 上游：server_port_Class；下游：Linux ss（iproute2）

class server_port_nix_Class
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

    // 讀監聽中嘅 TCP 埠（ss -ltnp；回 [port => pid]）
    public function get_listening_ports()
    {
        $command_output_lines = [];
        @exec("ss -ltnp", $command_output_lines);

        return $this->parse_listening_ports($command_output_lines);
    }

    // 解析 ss 輸出（LISTEN 行；欄位 State／Recv-Q／Send-Q／Local／Peer／Process）
    private function parse_listening_ports($command_output_lines)
    {
        $listening_ports = [];

        // 逐行解析（LISTEN 行；欄位 State／Recv-Q／Send-Q／Local／Peer／Process）
        foreach ($command_output_lines as $command_output_line_item) {
            $command_output_columns = preg_split('/\s+/', trim($command_output_line_item));

            // 判定：欄位不足（表頭等）→ 跳
            if (count($command_output_columns) < 5) {
                continue;
            }

            // 判定：非 LISTEN → 跳
            if ($command_output_columns[0] !== "LISTEN") {
                continue;
            }

            $local_port = $this->get_port_from_colon_address($command_output_columns[3]);

            // 判定：解析唔到 port → 跳
            if ($local_port === 0) {
                continue;
            }

            $listener_pid = $this->get_listener_pid($command_output_line_item);

            $listening_ports[$local_port] = $listener_pid;
        }

        return $listening_ports;
    }

    // 由 Process 欄抽 pid（無權限時冇 pid= → 0）
    private function get_listener_pid($command_output_line)
    {
        $pid_match = [];

        // 判定：冇 pid= → 0
        if (preg_match('/pid=(\d+)/', $command_output_line, $pid_match) !== 1) {
            return 0;
        }

        return (int)$pid_match[1];
    }

    // 由「ip:port」取 port（含 [::]:8080 形式）；0＝解析唔到
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
