<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_msg_conf_data：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-09-27 自 poll/file.php 分獨立檔（one file one function） ==
// == 2026-10-08 目錄制改 messages/polling/{project_slug}/{message_id}——加 by_slug 入口（掃描用）；讀取收斂入 read_msg_conf_file ==

trait get_msg_conf_data
{

    // 讀 msg_conf（msg_conf.json——執行參數來源）→ 直接回解碼後陣列（缺失／唔合法 → null）
    public function get_msg_conf_data($chat_id, $message_id)
    {
        $conf_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "conf");

        return $this->read_msg_conf_file($conf_file);
    }

    // 讀 msg_conf（目錄名已知：messages/polling/{project_slug}/{message_id}——掃描入口，免查 chat 列）
    public function get_msg_conf_data_by_slug($project_slug, $message_id)
    {
        $conf_file = sc_ai_chats()->poll()->get_poll_file_by_slug($project_slug, $message_id, "conf");

        return $this->read_msg_conf_file($conf_file);
    }

    // 讀 conf 檔（單一讀取來源——兩個入口共用；缺失／唔合法 → null）
    private function read_msg_conf_file($conf_file)
    {
        // 判定：conf 檔唔存在 → null
        if (!is_file($conf_file)) {
            return null;
        }

        $conf_json = json_decode(file_get_contents($conf_file), true);

        // 判定：唔合法 JSON → null
        if (!is_array($conf_json)) {
            return null;
        }

        return $conf_json;
    }

}
