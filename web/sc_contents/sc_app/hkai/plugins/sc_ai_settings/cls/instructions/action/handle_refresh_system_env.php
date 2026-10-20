<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\action;

// == sc_ai_settings instructions handle_refresh_system_env：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==
// == 職責：手動更新系統級環境（轉呼 diagnostics 重寫 manifest 環境檔；storage 工作副本同步失效——下次開頁重新 copy） ==

trait handle_refresh_system_env
{
    // POST settings_refresh_system_env handler（Environment 頁更新掣——執行歸 sc_ai_diagnostics system_env，此處轉呼＋清 storage 副本）
    public function handle_refresh_system_env()
    {
        $write_result = sc_ai_diagnostics()->system_env()->write_system_env_file(true);

        // 判定：寫入失敗（bridge 未運行／偵測失敗）→ 500（帶原因）
        if ($write_result["ok"] !== true) {
            return sc_response()->json(["ok" => false, "error" => $write_result["error"]], 500);
        }

        // 清走 storage 工作副本（下次開頁由 manifest 新檔重新 copy——畫面即時見新事實）
        $paths = $this->get_instructions_paths("system_env");
        @unlink($paths["storage_file"]);

        return sc_response()->json(["ok" => true, "detected_at" => $write_result["detected_at"]]);
    }
}
