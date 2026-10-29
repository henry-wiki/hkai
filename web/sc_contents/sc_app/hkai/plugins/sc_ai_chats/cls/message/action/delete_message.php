<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\action;

// == sc_ai_chats message delete_message：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/action.php 分獨立檔（one file one function） ==

trait delete_message
{

    //---------------------------------------------------------
    // 1. 刪 DB（回應＋訊息＋重算數）
    // 2. 刪訊息檔案
    // 3. 對話清空＝連對話一齊刪
    //---------------------------------------------------------
    // 回傳：["chat_deleted" => bool]（true ＝ 對話已連帶清掉——前端導向專案根）
    public function delete_message($message_row)
    {
        $chat_id      = (int)$message_row["chat_id"];
        $message_id   = (int)$message_row["message_id"];

        // 診斷 log：開始
        hkai()->runtime()->logger()->info("delete_message start: chat_id=" . $chat_id . " message_id=" . $message_id);

        // 身份（project_slug 由 chat 列解析——路徑唯一來源 get_poll_dir_name）
        $chat_row     = sc_ai_chats()->chat()->get_chat_row($chat_id);
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        // 判定：專案 slug 空（專案已刪）→ 拋錯（唔好半刪）
        if ($project_slug === "") {
            throw new \RuntimeException("delete_message missing project_slug: chat_id=" . $chat_id);
        }

        // 停止回應（共用入口：停抽取＋標 cancelled＋殺 worker 樹——同 Stop 掣同一支）
        sc_ai_chats()->prompt()->handle_prompt_stop($message_id);

        // msg 檔案硬刪（現行箱＋歸檔箱——各自域路徑層）
        sc_ai_chats()->poll()->delete_poll_files($project_slug, $message_id);
        sc_ai_chats()->archive()->delete_archive_files($project_slug, $message_id);

        // 永久附件庫硬刪（<base>/<slug>/<sid>/<prompt_id>——附件已於出帶時搬出箱）
        $prompt_id = (string)($message_row["prompt_id"] ?? "");

        // 判定：有 prompt_id → 刪附件庫目錄
        if ($prompt_id !== "") {
            $attachments_store_dir = sc_get_config("hkai.messages_attachments_dir") . "/" . $project_slug . "/" . (string)$chat_row["chat_sid"] . "/" . $prompt_id;
            sc_rrmdir($attachments_store_dir);
        }

        // 診斷 log：msg 檔案完成
        hkai()->runtime()->logger()->info("delete_message msg files removed");

        // DB 硬刪（message_response 列＋messages 列＋重算 chat count——repo_update 單一入口）
        sc_ai_chats()->message()->repo_update()->delete_db_message($message_id, $chat_id);

        // 診斷 log：DB 列完成
        hkai()->runtime()->logger()->info("delete_message db rows removed");

        $remove_result = [];
        $remove_result["chat_deleted"] = false;

        // 判定：對話已無訊息（剛刪掉最後一則）→ 先看仲有冇排隊中訊息
        if (sc_ai_chats()->chat()->is_chat_empty($chat_id)) {
            $queue_items = sc_ai_chats()->queue()->get_queue_items($project_slug, (string)$chat_row["chat_sid"]);

            // 判定：仲有排隊 → 保留對話列（等待列要 chat row 才顯示得到／派得到工）
            if (count($queue_items) > 0) {
                hkai()->runtime()->logger()->info("delete_message chat kept (queue not empty): chat_id=" . $chat_id);

                return $remove_result;
            }

            // chats 列硬刪（＋殘留關聯列保險清）
            sc_ai_chats()->chat()->delete_db_chat($chat_id);

            // 診斷 log：對話已空 → 連帶清咗
            hkai()->runtime()->logger()->info("delete_message chat emptied -> chat removed: chat_id=" . $chat_id);

            // 對話已清 → 前端無痕導向專案根（/{project_slug}/——前端用自身 project_slug SSOT 砌網址，後端唔回導向目標）
            $remove_result["chat_deleted"] = true;
        }

        return $remove_result;
    }

}
