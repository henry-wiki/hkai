<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\normalize;

// == sc_ai_chats message normalize\response_brief_rows：normalize 子域動作（trait——合成入 normalize_Class；檔＝動作：簡版回應列轉 map） ==

trait response_brief_rows
{
    // 簡版回應列（讀取果）轉 map——key＝poll_file（poll 對照：存在／凍結判定用）
    public function response_brief_rows($response_brief_rows)
    {
        $response_brief_row_map = [];

        // 逐行轉 map（key＝poll_file）
        foreach ($response_brief_rows as $response_brief_row_item) {
            $response_brief_row_map[$response_brief_row_item["poll_file"]] = $response_brief_row_item;
        }

        return $response_brief_row_map;
    }

}
