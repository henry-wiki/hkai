<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat_option;

// == sc_ai_chats chat_option get_chat_mode：域動作（trait——掛入 chat_option_Class；檔＝動作） ==
// == 2026-10-02 自 sc_ai_providers main 域遷入（對話選項歸 chats——chat_option 域） ==

trait get_chat_mode
{
    // chat_mode 驗證（傳入值係現行引擎組 UI 模條目 → 回傳；唔係／空 → 回第一條）
    public function get_chat_mode($chat_mode)
    {
        // 合法值集＝現行引擎組 chat_mode UI 模條目（有 mode 欄嗰啲 key）
        $daemon_engine         = hkai__core()->get_daemon_engine();
        $daemon_engine_configs = hkai__core()->get_hkai_configs($daemon_engine);
        $chat_mode_config      = $daemon_engine_configs["chat_mode"];

        $valid_chat_modes = [];
        // 逐個 UI 模條目收集 key（value 有 mode 欄＝UI 模）
        foreach ($chat_mode_config as $chat_mode_name => $chat_mode_item) {
            // 判定：唔係 UI 模條目（無 mode 欄）→ 跳
            if (!isset($chat_mode_item["mode"])) {
                continue;
            }
            $valid_chat_modes[] = $chat_mode_name;
        }

        if (in_array($chat_mode, $valid_chat_modes)) {
            return $chat_mode;
        }

        return $valid_chat_modes[0];
    }
}
