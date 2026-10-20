<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat_option;

// == sc_ai_chats chat_option get_chat_options：域動作（trait——掛入 chat_option_Class；檔＝動作） ==
// == 2026-10-02 自 sc_ai_providers main 域遷入（對話選項歸 chats——chat_option 域） ==

trait get_chat_options
{
    // 當前 chat options 解析（chat row → SESSION → 預設——回傳附 source 供 UI 標示）
    public function get_chat_options($project_slug, $chat_sid)
    {
        $model_source = "";
        $provider_id = "";
        $model_id = "";
        $reasoning_effort = "";
        $chat_mode = "";
        
        
        // 來源 1：chat row 自帶 model（該 chat 已選用過——provider 欄有值即用）
        $chat_row = null;
        if ($chat_sid) {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
            if ($chat_row) {
                $provider_id = $chat_row["provider_id"];
                $model_id = $chat_row["model_id"];
                $reasoning_effort = $chat_row["reasoning_effort"];
                $chat_mode = $chat_row["chat_mode"];
                
                if($provider_id)
                {
                    $model_source = "chat";
                }
                
                //return $this->build_selected_model("chat", $chat_row["provider_id"], $chat_row["model_id"], $chat_row["reasoning_effort"]);
            }
        }
        
        // 來源 2：SESSION（畫面內已選——新 chat 未落地前的兜底）
        if(!@$provider_id)
        {
            $session_chat_options = sc_session()->get("chat_options");
            
            if($session_chat_options)
            {
                
                $provider_id = $session_chat_options["provider_id"];
                $model_id = $session_chat_options["model_id"];
                $reasoning_effort = $session_chat_options["reasoning_effort"];
                $chat_mode = isset($session_chat_options["chat_mode"]) ? $session_chat_options["chat_mode"] : "";
                
                if($provider_id)
                {
                    $model_source = "session";
                }
            }
            
        }
        
        
        // chat_mode 兜底（chat row 未帶——舊對話；補讀 SESSION）
        if($chat_mode === "")
        {
            $session_chat_options = sc_session()->get("chat_options");
            
            if($session_chat_options)
            {
                $chat_mode = isset($session_chat_options["chat_mode"]) ? $session_chat_options["chat_mode"] : "";
            }
        }

        // --------------------------------------------------------------------------------
        
        $_chat_options = [];
        $_chat_options["source"] = $model_source;
        $_chat_options["provider_id"] = $provider_id;
        $_chat_options["model_id"] = $model_id;
        $_chat_options["reasoning_effort"] = $reasoning_effort;
        $_chat_options["chat_mode"] = $chat_mode;
        
        return $_chat_options;
    }
}
