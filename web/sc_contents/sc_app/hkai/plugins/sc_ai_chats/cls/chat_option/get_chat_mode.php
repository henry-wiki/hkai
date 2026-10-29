<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat_option;

// == sc_ai_chats chat_option get_chat_mode：域動作（trait——掛入 chat_option_Class；檔＝動作） ==
// == 2026-10-02 自 sc_ai_providers main 域遷入（對話選項歸 chats——chat_option 域） ==

trait get_chat_mode
{
    // chat_mode 驗證（傳入值係 key 或舊資料嘅 mode 值 → 回 key；唔係／空 → 回第一條）
    public function get_chat_mode($chat_mode)
    {
        // 合法值集＝現行agent組 chat_mode UI 模條目（有 mode 欄嗰啲 key）
        $agent           = hkai()->agent()->get_agent();
        $agent_daemon    = hkai()->agent()->get_agent_daemon($agent);
        $daemon_configs  = hkai()->get_hkai_configs($agent_daemon);
        $chat_mode_config = $daemon_configs["chat_mode"];

        $valid_chat_modes = [];
        $chat_mode_key_by_mode = [];

        // 逐個 UI 模條目收集 key（value 有 mode 欄＝UI 模）＋ mode 值反查表
        foreach ($chat_mode_config as $chat_mode_name => $chat_mode_item) {
            // 判定：唔係 UI 模條目（無 mode 欄）→ 跳
            if (!isset($chat_mode_item["mode"])) {
                continue;
            }

            $valid_chat_modes[] = $chat_mode_name;
            $chat_mode_key_by_mode[$chat_mode_item["mode"]] = $chat_mode_name;
        }

        // 判定：直接命中 key → 回傳
        if (in_array($chat_mode, $valid_chat_modes)) {
            return $chat_mode;
        }

        // 判定：舊資料存嘅係 mode 值（chat／smart_approve／auto）→ 反查返 key
        if (isset($chat_mode_key_by_mode[$chat_mode])) {
            return $chat_mode_key_by_mode[$chat_mode];
        }

        // 判定：更舊值 smart（舊詞彙）→ 對應 smart_approve 嗰條
        if ($chat_mode === "smart") {
            // 判定：有 smart_approve 對映 → 回傳
            if (isset($chat_mode_key_by_mode["smart_approve"])) {
                return $chat_mode_key_by_mode["smart_approve"];
            }
        }

        return $valid_chat_modes[0];
    }
}
