<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\normalize;

// == sc_ai_chats message normalize\get_response_type_from_poll_file：normalize 子域動作（trait——合成入 normalize_Class；檔＝動作） ==

trait get_response_type_from_poll_file
{
    // 由 poll_file 檔名拆 response_type（msg_{family}.{index}.{kind}.txt——family_kind；answer 已統一 result）
    public function get_response_type_from_poll_file($poll_file)
    {
        $file_name_parts = explode(".", pathinfo($poll_file, PATHINFO_FILENAME));

        // 尾段＝型別後綴
        $response_type_subfix = (string)array_pop($file_name_parts);

        // 首段＝family（去 msg_ 前綴）
        $response_family = substr((string)$file_name_parts[0], strlen("msg_"));

        return $response_family . "_" . $response_type_subfix;
    }

}
