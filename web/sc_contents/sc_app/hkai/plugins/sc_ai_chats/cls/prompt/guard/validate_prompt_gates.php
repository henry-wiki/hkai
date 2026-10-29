<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\guard;

// == sc_ai_chats prompt validate_prompt_gates：prompt 域守衛（trait——掛入 prompt_Class；cls/prompt/guard/） ==
// == 職責：兩關驗證（伺服器就緒／可處理性）——通過就交回 context_length，失敗就寫入 error
// == 上游：prompt_send()->prepare_prompt_request（工序 3，前置必填已由工序 2 驗）；下游：prompt processable validator＋process server 狀態

trait validate_prompt_gates
{
    // 三關驗證（前置條件／伺服器就緒／可處理性）
    public function validate_prompt_gates($context)
    {
        $prompt_input = [];
        $prompt_input["provider_id"]      = $context["provider_id"];
        $prompt_input["model_id"]         = $context["model_id"];
        $prompt_input["reasoning_effort"] = $context["reasoning_effort"];

        // 第一關：伺服器就緒（bridge 唔活＝E_SERVER_DOWN）
        $server_status = hkai()->process()->server()->get_server_status();

        // 判定：bridge 唔活 → 記錄統一錯誤結構
        if ($server_status["is_alive"] !== true) {
            $context["error"] = [
                "ok"      => false,
                "code"    => "E_SERVER_DOWN",
                "title"   => hkai()->i18n()->get("error.E_SERVER_DOWN.title"),
                "message" => "無法連接本機伺服器，訊息未送出。",
                "hint"    => "請先啟動伺服器（Settings → Server →「啟用」）再重新發送。",
            ];
            $context["error_status"] = 503;
            return $context;
        }

        // 第二關：可處理性（model／context_length／effort）
        $proc_result = sc_ai_chats()->prompt()->validate_prompt_processable($prompt_input);

        // 判定：第二關失敗 → 記錄錯誤
        if ($proc_result["ok"] === false) {
            $context["error"]        = $proc_result;
            $context["error_status"] = 400;
            return $context;
        }

        $context["context_length"] = $proc_result["context_length"];

        return $context;
    }
}
