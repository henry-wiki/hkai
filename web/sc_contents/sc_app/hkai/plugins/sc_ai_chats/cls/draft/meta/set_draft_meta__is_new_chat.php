<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\meta;

// == sc_ai_chats draft set_draft_meta__is_new_chat：草稿 meta 寫入（trait——掛入 draft_Class） ==
// == 職責：project_slug＋chat_sid → meta is_new_chat=0（冇草稿目錄＝唔動作，唔建空 meta） ==
// == 上游：prompt_send（新對話落地後）；下游：draft_Class 路徑＋update_meta ==

trait set_draft_meta__is_new_chat
{

    // 草稿 meta 寫 is_new_chat=0（回 true／false——冇草稿唔動作）
    public function set_draft_meta__is_new_chat($project_slug, $chat_sid)
    {
        $draft_meta = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);

        // 判定：冇草稿 → 唔動作
        if (count($draft_meta) < 1) {
            return false;
        }

        return sc_ai_chats()->draft()->set_draft_meta($project_slug, $chat_sid, ["is_new_chat" => 0]);
    }
}
