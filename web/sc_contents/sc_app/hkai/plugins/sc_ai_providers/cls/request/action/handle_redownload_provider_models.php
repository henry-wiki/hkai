<?php

namespace sc\app\hkai\plugins\sc_ai_providers\request\action;

// == sc_ai_providers request handle_redownload_provider_models：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_providers_request 分遷入（one file one action） ==

trait handle_redownload_provider_models
{
    // POST redownload_provider_models：provider_id 必填——單一 provider 重新下載（具名分派——kilo／openrouter 包內 redownload_provider_models）
    public function handle_redownload_provider_models()
    {
        // 讀入 provider_id（JSON body——必填）
        $input_data = sc_request()->json();
        $provider_id = isset($input_data["provider_id"]) ? $input_data["provider_id"] : "";
        
        // provider_id 必填（缺失 → 400）
        if ($provider_id === "") {
            return sc_response()->json(["ok" => false, "error" => "provider_id required"], 400);
        }
        
        // 重新下載（具名分派——kilo／openrouter 直接跳；F12 單一 target；數據由包內自取）
        if ($provider_id === "kilo") {
            $result = sc_ai_providers()->instance__kilo()->redownload_provider_models();
        } elseif ($provider_id === "openrouter") {
            $result = sc_ai_providers()->instance__openrouter()->redownload_provider_models();
        } else {
            // 無對應 provider（唔係 kilo／openrouter）→ 400（前端顯示失敗）
            return sc_response()->json(["ok" => false, "error" => "unknown provider: " . $provider_id], 400);
        }
        
        return sc_response()->json(["ok" => true, "results" => [$provider_id => $result]]);
    }
}
