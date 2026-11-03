<?php

namespace sc\app\hkai\cls\process\bridge;

// == bridge_watch_Class：bridge 心跳等待（singleton——經 hkai()->process()->bridge_watch() 取得）
// == 職責：① wait_heartbeat_removed() 等心跳檔消失（停止） ② wait_heartbeat_started() 等心跳檔出現（啟動）
// == 上游：server（起／停）
// == 下游：process_status/bridge.{pid}.json（唯一狀態來源）

class bridge_watch_Class
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

    // 等心跳檔消失（每 0.25 秒查檔；回 true＝已消失、false＝逾時仍在）
    public function wait_heartbeat_removed($process_pid, $timeout_sec)
    {
        $process_status_dir = sc_get_config("hkai.process_status_dir");
        $heartbeat_file     = $process_status_dir . "/bridge." . (int)$process_pid . ".json";
        $max_attempts       = (int)($timeout_sec * 4);

        // 每 0.25 秒查一次心跳檔
        for ($attempt_index = 0; $attempt_index < $max_attempts; $attempt_index++) {
            usleep(250000);

            // 清 stat 快取（PHP 會記住 is_file 結果——唔清就永遠見到舊檔）
            clearstatcache(true, $heartbeat_file);

            // 判定：心跳檔已消失 → 完成
            if (!is_file($heartbeat_file)) {
                return true;
            }
        }

        return false;
    }

    // 等 bridge 心跳出現（每 0.25 秒掃 process_status；拉起之後寫嘅 heartbeat＝已就緒）
    public function wait_heartbeat_started($server_wait_timeout_sec, $not_before_time)
    {
        $max_attempts = (int)($server_wait_timeout_sec * 4);

        for ($attempt_index = 0; $attempt_index < $max_attempts; $attempt_index++) {
            usleep(250000);

            $heartbeat_paths = hkai()->process()->list_bridge_heartbeat_files();

            foreach ($heartbeat_paths as $heartbeat_path) {
                clearstatcache(true, $heartbeat_path);

                // 判定：心跳檔係拉起之後才寫（舊檔唔算）→ 已就緒
                if ((int)@filemtime($heartbeat_path) < $not_before_time) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }
}
