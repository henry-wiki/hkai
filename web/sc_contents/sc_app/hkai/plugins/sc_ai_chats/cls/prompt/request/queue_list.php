<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt queue_list：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：回傳等待列完整 HTML） ==
// == 職責：queue_list（POST 端點——同一渲染源 render_queue_list；未選對話／查無＝空列） ==
// == 2026-09-27 歸 request 層（POST 端點）；自 view.php 分獨立檔（檔＝動作） ==

trait queue_list
{

    // POST queue_list：回傳該對話等待列完整 HTML（與頁面初始／submit／poll 同一渲染源 render_queue_list）
    public function queue_list()
    {
        $input_data = sc_request()->json();

        $chat_sid = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        $queue_list_html = $this->render_empty_queue_list();

        // 判定：已選對話 → 解析列＋掃等待列砌完整 UI（同一 render_queue_list）
        if ($chat_sid !== "") {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

            // 判定：查無對話 → 內容回報失敗（HTTP 仍 200——同 prompt_fetch）
            if ($chat_row === null) {
                return sc_response()->json(["ok" => false, "error" => "chat not found"]);
            }

            $queue_list_html = sc_ai_chats()->prompt()->render_queue_list((int)$chat_row["chat_id"]);
        }

        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "queue_list" => $queue_list_html,
            ],
        ]);
    }
}
