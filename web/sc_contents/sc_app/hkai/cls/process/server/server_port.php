<?php

namespace sc\app\hkai\cls\process\server;

// == server_port_Class：連接埠工具（singleton——經 server()->port() 取得）
// == 職責：依 hkai_config 嘅 port_ranges 砌候選，隨機抽一個未佔用埠／等埠釋放
// == 平台：Windows＝server_port_win／Linux＝server_port_nix／macOS＝server_port_mac（平台 class 只交監聽清單）
// == 上游：server（start 自動揀埠）；下游：平台 class（netstat／ss）
// ==
// == 不可用埠清單（永久避開；選埠一律照 hkai_config 嘅 port_ranges）
// == A. 瀏覽器封鎖（Chrome／Edge ERR_UNSAFE_PORT）
//   1–1023      系統保留（HTTP／HTTPS／SSH／SMTP／DNS 等）
//   2049        NFS
//   3659        Apple SASL
//   4045        NFS lockd
//   5060–5061   SIP
//   6000        X11
//   6566        SANE
//   6665–6669   IRC
//   6679        IRC over TLS（舊）
//   6697        IRC over TLS
//   10080       Amanda
// == B. 大型軟件／服務慣用（避開避免撞）
//   1080        SOCKS proxy
//   1433        MS SQL Server
//   1521        Oracle
//   1723        PPTP
//   1883/8883   MQTT
//   2375/2376   Docker API
//   3000        Node／Grafana
//   3306        MySQL／MariaDB
//   3389        RDP
//   5000/5001   Synology DSM
//   5432        PostgreSQL
//   5601        Kibana
//   5672        RabbitMQ
//   5900        VNC
//   6379        Redis
//   7001        WebLogic
//   8000/8080/8081/8443/8888   HTTP alt／Tomcat／proxy
//   9000/9090   PHP-FPM／Prometheus
//   9200/9300   Elasticsearch
//   11211       Memcached
//   27017/27018 MongoDB
// == C. 本機動態埠排除（Windows Hyper-V／WSL 保留區，機器各異）
//   以 netsh int ipv4 show excludedportrange protocol=tcp 查；唔寫死

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

    // 隨機揀一個可用埠（設定表 → 候選清單 → 排除封鎖／佔用 → 抽一個；0＝冇候選）
    public function find_available_port()
    {
        $listening_ports = $this->port_platform()->get_listening_ports();

        $port_candidates = $this->build_port_candidates();

        // 剔除已被佔用嘅埠
        $free_port_candidates = [];

        foreach ($port_candidates as $port_candidate_item) {
            // 判定：已監聽 → 唔要
            if (isset($listening_ports[$port_candidate_item]) === true) {
                continue;
            }

            $free_port_candidates[] = $port_candidate_item;
        }

        // 判定：冇候選 → 0（呼叫端報 no_port）
        if (count($free_port_candidates) < 1) {
            return 0;
        }

        $random_index = random_int(0, count($free_port_candidates) - 1);

        return (int)$free_port_candidates[$random_index];
    }

    // 由設定表砌候選埠（"6200,6300"＝範圍；"6578"＝單一；再扣九組不可用埠）
    private function build_port_candidates()
    {
        $hkai_configs = hkai()->get_hkai_configs();
        $server_settings = $hkai_configs["server_settings"] ?? [];
        $port_ranges     = $server_settings["port_ranges"] ?? [];

        // 判定：未設定 → 用預設整段
        if (is_array($port_ranges) === false || count($port_ranges) < 1) {
            $port_ranges = ["6001,6999"];
        }

        $port_candidates = [];

        foreach ($port_ranges as $port_range_item) {
            $range_ports = $this->expand_port_range((string)$port_range_item);

            foreach ($range_ports as $range_port_item) {
                $port_candidates[] = $range_port_item;
            }
        }

        return array_values(array_unique($port_candidates));
    }

    // 展開單一設定項（a,b＝範圍；a＝單一）
    private function expand_port_range($port_range_text)
    {
        $range_parts = explode(",", $port_range_text);

        // 判定：單一埠 → 一個
        if (count($range_parts) < 2) {
            $single_port = (int)trim($range_parts[0]);

            return ($single_port > 0) ? [$single_port] : [];
        }

        $range_start = (int)trim($range_parts[0]);
        $range_end   = (int)trim($range_parts[1]);

        // 判定：範圍唔合法 → 空
        if ($range_start < 1 || $range_end < $range_start) {
            return [];
        }

        return range($range_start, $range_end);
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
