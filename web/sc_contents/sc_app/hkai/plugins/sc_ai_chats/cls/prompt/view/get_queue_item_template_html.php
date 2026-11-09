<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt view get_queue_item_template_html：等待列 chip 模板（**唔另砌 markup——委派 build_queue_item_html**） ==
// == 職責：用空資料叫 build_queue_item_html()，交出 chip 文字（唔加外殼——外殼由 render_chat_prompt 包成隱藏 div） ==
// == 上游：render_chat_prompt（base64 隱藏交付入 composer） ==
// == 下游：render_queue_list 內的 build_queue_item_html（唯一 chip markup 產生者）

trait get_queue_item_template_html
{
    // 單個 chip 骨架（唯一真相＝build_queue_item_html）
    public function get_queue_item_template_html()
    {
        $empty_queue_item = [];
        $empty_queue_item["prompt_id"]   = "";
        $empty_queue_item["prompt_text"] = "";

        return sc_ai_chats()->prompt()->build_queue_item_html($empty_queue_item);
    }
}
