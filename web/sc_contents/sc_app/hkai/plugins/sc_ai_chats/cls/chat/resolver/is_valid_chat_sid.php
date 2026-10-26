<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat is_valid_chat_sid：chat 域解析（trait——掛入 chat_Class；檔＝動作） ==
// == 職責：判定 chat_sid 格式（32 hex——chat_sid 唯一合法形；大小寫不敏感）
// == 上游：prompt_fetch（新 prompt／錯 sid 分流）；下游：無

trait is_valid_chat_sid
{

    // 判定 chat_sid 係唔係合法格式（32 hex）
    public function is_valid_chat_sid($chat_sid)
    {
        $chat_sid = (string)($chat_sid ?? "");

        // 判定：32 hex → 合法；其餘唔合法
        if (preg_match("/^[0-9a-f]{32}$/i", $chat_sid) === 1) {
            $is_valid_chat_sid = true;
        } else {
            $is_valid_chat_sid = false;
        }

        return $is_valid_chat_sid;
    }

}
