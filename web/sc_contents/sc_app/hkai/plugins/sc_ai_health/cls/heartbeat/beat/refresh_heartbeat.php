<?php

namespace sc\app\hkai\plugins\sc_ai_health\heartbeat\beat;

// == sc_ai_health heartbeat refresh_heartbeat：heartbeat 域動作（trait——掛入 heartbeat_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat heartbeat_Class 分遷入（one file one action） ==

trait refresh_heartbeat
{
    // 刷新心跳（初始化/建立檔案→讀取→count+1→寫入→回傳）
    public function refresh_heartbeat()
    {
        $daemon_config         = hkai__core()->get_daemon_config();
        $daemon_heartbeat_path = $daemon_config["daemon_heartbeat_path"];

        // 心跳檔不存在 → 建立初始結構（已存在 → 不做任何事）
        if (!file_exists($daemon_heartbeat_path)) {
            $heartbeat_data["created_at"]  = time();
            $heartbeat_data["updated_at"]  = time();
            $heartbeat_data["age_seconds"] = 0;
            $heartbeat_data["beat_count"]  = 0;
            $heartbeat_data["is_alive"]    = false;
            file_put_contents($daemon_heartbeat_path, json_encode($heartbeat_data), LOCK_EX);
        }

        // 1. get heartbeat data（讀取＋count+1——count 累積在 get_heartbeat_data 內）
        $heartbeat_data = $this->get_heartbeat_data();

        // 2. 寫入心跳檔（file_put_contents——寫入職責在本方法）
        file_put_contents($daemon_heartbeat_path, json_encode($heartbeat_data), LOCK_EX);

        // 3. return heartbeat data
        return $heartbeat_data;
    }
}
