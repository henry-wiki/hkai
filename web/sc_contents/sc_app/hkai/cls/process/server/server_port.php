<?php

namespace sc\app\hkai\cls\process\server;

// == server_port_Class：連接埠工具（singleton——經 server()->port() 取得）
// == 職責：隨機揀可用埠（6001–6999，監聽快照＋最多 9 次抽）／等埠釋放
// == 平台：Windows＝server_port_win／Linux＝server_port_nix／macOS＝server_port_mac（平台 class 只交監聽清單）
// == 上游：server（start 自動揀埠）；下游：平台 class（netstat／ss）

class server_port_Class
{
    private static $instance = null;

    // 平台實作（首次要用才建立）
    private $port_platform = null;

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

    // 隨機揀一個可用埠（一次監聽快照＋最多 9 次抽；0＝9 次都撞）
    public function find_available_port($port_range_start = 6001, $port_range_end = 6999, $max_attempts = 9)
    {
        $listening_ports = $this->port_platform()->get_listening_ports();

        // 逐次抽（每次一個隨機埠；未監聽即回）
        for ($attempt_index = 0; $attempt_index < $max_attempts; $attempt_index++) {
            $port_candidate = random_int($port_range_start, $port_range_end);

            // 判定：已監聽 → 再抽
            if (isset($listening_ports[$port_candidate])) {
                continue;
            }

            return $port_candidate;
        }

        return 0;
    }

    // 平台實作（Windows／macOS 明確對應；其餘一律當 unix）
    private function port_platform()
    {
        // 判定：已建立 → 重用
        if ($this->port_platform !== null) {
            return $this->port_platform;
        }

        $os_family = PHP_OS_FAMILY;

        $platform_class_map = [
            "Windows" => server_port_win_Class::class,
            "Darwin"  => server_port_mac_Class::class,
        ];

        $default_platform_class = server_port_nix_Class::class;
        $platform_class         = $platform_class_map[$os_family] ?? $default_platform_class;

        $this->port_platform = $platform_class::getInstance();

        return $this->port_platform;
    }
}
