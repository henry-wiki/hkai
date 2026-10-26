<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\action;

// == sc_ai_settings instructions handle_refresh_system_env：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==
// == 職責：手動更新系統級環境（bridge JSON → PHP 砌 Markdown → 寫 data/instructions/system_env.md） ==

trait handle_refresh_system_env
{
    // POST settings_refresh_system_env handler（Environment 頁更新掣——交 instruction()->system_env() 同步）
    public function handle_refresh_system_env()
    {
        $is_synced = hkai()->process()->instruction()->system_env()->sync_sys_env();

        // 判定：同步失敗（bridge 未運行／偵測失敗）→ 500
        if ($is_synced !== true) {
            return sc_response()->json(["ok" => false, "error" => "system env sync failed"], 500);
        }

        return sc_response()->json(["ok" => true]);
    }
}
