<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\meta;

// == sc_ai_chats prompt get_prompt_meta：prompt 域讀取（trait——掛入 prompt_Class；檔＝動作） ==
// == 職責：prompt meta＝草稿輸入＋交付欄位嘅單一形狀（17 key）；msg_conf 由佢剔走 PHP 專用欄位砌成 ==
// == 上游：prompt_send()->prepare_prompt_request（取草稿值）；下游：queue materialize（蓋章寫檔） ==

trait get_prompt_meta
{
    // 草稿 meta → prompt meta（草稿 5 項覆蓋模板；其餘留預設，由後續工序填）
    public function get_prompt_meta($draft_meta)
    {
        $prompt_meta = $this->_get_prompt_meta_template();

        // 輸入：問題內容
        $prompt_meta["content"] = trim((string)($draft_meta["prompt_text"] ?? ""));

        // 模型：草稿揀嘅 provider／model／mode／effort
        $prompt_meta["provider_id"]      = trim((string)($draft_meta["provider_id"] ?? ""));
        $prompt_meta["model_id"]         = trim((string)($draft_meta["model_id"] ?? ""));
        $prompt_meta["chat_mode"]        = trim((string)($draft_meta["chat_mode"] ?? ""));
        $prompt_meta["reasoning_effort"] = $draft_meta["reasoning_effort"] ?? null;

        return $prompt_meta;
    }

    // prompt meta 模板（17 key 一處宣告——分組次序：去向 → 輸入 → 模型 → 統計 → 執行）
    private function _get_prompt_meta_template()
    {
        $prompt_meta_template = [];

        // 去向：專案／對話／訊息
        $prompt_meta_template["project_path"]     = "";
        $prompt_meta_template["project_id"]       = 0;
        $prompt_meta_template["project_slug"]     = "";
        $prompt_meta_template["chat_id"]          = 0;
        $prompt_meta_template["chat_sid"]         = "";
        $prompt_meta_template["message_id"]       = 0;

        // 輸入：問題本身＋附件清單
        $prompt_meta_template["content"]          = "";
        $prompt_meta_template["attachments"]      = [];

        // 模型：揀邊個模型、點跑、容量上限
        $prompt_meta_template["provider_id"]      = "";
        $prompt_meta_template["model_id"]         = "";
        $prompt_meta_template["chat_mode"]        = "";
        $prompt_meta_template["reasoning_effort"] = null;
        $prompt_meta_template["context_length"]   = 0;

        // 統計：框架預留（現階段固定 0）
        $prompt_meta_template["input_token"]      = 0;
        $prompt_meta_template["complete_token"]   = 0;

        // 執行：落地時由 materialize 蓋章
        $prompt_meta_template["agent"]            = "";
        $prompt_meta_template["daemon_engine"]    = "";

        return $prompt_meta_template;
    }
}
