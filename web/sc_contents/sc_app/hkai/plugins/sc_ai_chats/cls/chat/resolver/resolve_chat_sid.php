<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat resolve_chat_sid：chat 域解析（trait——掛入 chat_Class；檔＝動作） ==
// == 職責：對話段 → ["archive" => 1／0, "chat_sid" => 純 sid]（語境形 archive@<chat_sid>）
// == 上游：chat／prompt／health request；下游：無（純解析）

trait resolve_chat_sid
{

    // 解析對話段（explode "@"——index 0＝archive 即標記；index 1＝chat_sid）
    public function resolve_chat_sid($raw_chat_sid)
    {
        $raw_chat_sid = (string)($raw_chat_sid ?? "");
        $chat_parts   = explode("@", $raw_chat_sid);

        $resolved = [];

        // 判定：index 0 係 archive（純 archive／archive@sid）→ 標記 1；否則 0
        if ($chat_parts[0] === "archive") {
            $resolved["archive"] = 1;
        } else {
            $resolved["archive"] = 0;
        }

        // 判定：有語境段（@ 後有值）→ sid 取 index 1；否則原值（chat 冇預設可 reset）
        if (count($chat_parts) > 1) {
            $resolved["chat_sid"] = $chat_parts[1];
        } else {
            $resolved["chat_sid"] = $chat_parts[0];
        }

        return $resolved;
    }

}
