<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment get_attachment_diskpath：附件路徑（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：附件列 → 絕對磁碟路徑（DB 存相對路徑；根＝storage 域，只有此處負責拼接） ==
// == 上游：prompt_send 落地②（砌 msg_conf 附件 path／圖片抽取清單） ==
// == 下游：storage get_storage_dir ==

trait get_attachment_diskpath
{
    // 由附件列取絕對路徑（呼叫方唔需要知 storage 根喺邊）
    public function get_attachment_diskpath($attachment_row)
    {
        $storage_dir = sc_ai_chats()->storage()->get_storage_dir();

        $attachment_filepath = (string)$attachment_row["filepath"];

        return $storage_dir . "/" . $attachment_filepath;
    }
}
