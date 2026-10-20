<?php

namespace sc\app\hkai\plugins\sc_ai_health\heartbeat\beat;

// == sc_ai_health heartbeat get_heartbeat_data：heartbeat 域動作（trait——掛入 heartbeat_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat heartbeat_Class 分遷入（one file one action） ==

trait get_heartbeat_data
{
    // 讀取心跳資料（完整結構——created_at/updated_at/age_seconds/beat_count/is_alive；無心跳檔時初始結構——純讀取不寫入）
    public function get_heartbeat_data()
    {
        $daemon_config         = hkai__core()->get_daemon_config();
        $daemon_heartbeat_path = $daemon_config["daemon_heartbeat_path"];

        $heartbeat_data = [];

        // 心跳檔存在 → 讀 JSON 內容
        if (file_exists($daemon_heartbeat_path)) {
            $heartbeat_data = file_get_contents($daemon_heartbeat_path);
            $heartbeat_data = json_decode($heartbeat_data, true);
        }

        // 舊檔兼容（heartbeat_* 舊鍵格式 → 視為無效重置，避免殘缺鍵）
        if (isset($heartbeat_data["heartbeat_create_at"])) {
            $heartbeat_data = null;
        }

        // 無資料（缺檔/損壞/舊格式）→ 初始結構
        if (!@$heartbeat_data)
        {
            $heartbeat_data                = [];
            $heartbeat_data["created_at"]  = time();
            $heartbeat_data["updated_at"]  = time();
            $heartbeat_data["age_seconds"] = 0;
            $heartbeat_data["beat_count"]  = 0;
            $heartbeat_data["is_alive"]    = false;
        }
        else
        {
            // 心跳年齡（秒——以檔案 mtime 計）
            if (file_exists($daemon_heartbeat_path)) {
                $heartbeat_data["age_seconds"] = time() - (int)filemtime($daemon_heartbeat_path);
            }

            // 心跳新鮮度（年齡 < 閾值 = 活——布林）
            $heartbeat_data["is_alive"] = $heartbeat_data["age_seconds"] < $this->stale_threshold_seconds;
        }

        // 計數 +1（每次讀取都算一次跳動）
        $heartbeat_data["beat_count"]              = (int)$heartbeat_data["beat_count"] + 1;
        $heartbeat_data["stale_threshold_seconds"] = $this->stale_threshold_seconds;

        return $heartbeat_data;
    }
}
