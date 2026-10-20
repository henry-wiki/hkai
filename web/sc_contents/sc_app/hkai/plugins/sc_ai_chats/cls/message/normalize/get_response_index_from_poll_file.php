<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\normalize;

// == sc_ai_chats message normalize\get_response_index_from_poll_file：normalize 子域動作（trait——合成入 normalize_Class；檔＝動作） ==

trait get_response_index_from_poll_file
{
    // 由 poll_file 檔名拆 response_index（msg_{family}.{index}.{kind}.txt——尾二段）
    public function get_response_index_from_poll_file($poll_file)
    {
        $file_name_parts = explode(".", pathinfo($poll_file, PATHINFO_FILENAME));

        // 先彈走尾段（kind）——再彈＝索引
        array_pop($file_name_parts);

        return (int)array_pop($file_name_parts);
    }

}
