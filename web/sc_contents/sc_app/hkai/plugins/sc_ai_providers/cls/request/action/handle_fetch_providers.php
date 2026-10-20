<?php

namespace sc\app\hkai\plugins\sc_ai_providers\request\action;

// == sc_ai_providers request handle_fetch_providers：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_providers_request 分遷入（one file one action） ==

trait handle_fetch_providers
{
    // POST fetch_providers：providers 清單＋當前選用（model 列表交 fetch_models 逐 provider 載入）
    public function handle_fetch_providers()
    {
        // 讀入頁面語境（project_slug / chat_sid——get_chat_options 解析用；可缺省）
        $input_data = sc_request()->json();
        $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";
        
        // 可用 provider（聲明——存在＝前台可用；providers 清單由此衍生；model 列表交 fetch_models 逐 provider 載入）
        $available_providers = sc_ai_providers()->main()->get_available_providers();
        $result = ["ok" => true];
        // providers 直接回傳聲明（key 為 provider_id——值＝資料袋）
        $result["providers"] = $available_providers;
        $result["selected"] = sc_ai_chats()->chat_option()->get_chat_options($project_slug, $chat_sid);
        
        // 頁面活動 scope（{project_id, chat_id}——chat_sid 解析 chat row；缺＝0）
        $result["activity_scope"] = ["project_id" => 0, "chat_id" => 0];
        
        // 判定：帶咗 chat_sid → 解析該對話補 scope
        if ($chat_sid !== "") {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
            
            // 判定：解析到 chat row → 寫入 project_id／chat_id
            if ($chat_row) {
                $result["activity_scope"]["project_id"] = (int)$chat_row["project_id"];
                $result["activity_scope"]["chat_id"]    = (int)$chat_row["chat_id"];
            }
        }
        
        
        
        // html：modal body（左側欄＋右內容——後端砌，前端只 insert；同設定 modal 做法）
        $result["html"] = [];
        $result["html"]["modal_body"] = $this->build_modal_body_html($available_providers);
        
        return sc_response()->json($result);
    }
}
