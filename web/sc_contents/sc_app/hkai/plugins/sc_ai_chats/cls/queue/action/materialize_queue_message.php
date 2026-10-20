<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue materialize_queue_message：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 2026-09-27 自 poll/action.php 分獨立檔；2026-09-27 歸 queue 域（隊頭落地成訊息——shift 之後一步） ==
// == 2026-10-08 加：複製專案 root AGENTS.md 入訊息資料夾（agent_rules 新機制——每訊息一份，零共享） ==

trait materialize_queue_message
{

    //---------------------------------------------------------
    // 1. 開訊息目錄
    // 2. 寫初始狀態＋起步 log
    // 3. 寫問題入 prompt 檔
    // 4. 複製專案規則（專案 root AGENTS.md——每訊息一份；無檔＝略過）
    // 5. 最後先寫 conf（＝開跑開關）
    //---------------------------------------------------------
    public function materialize_queue_message($chat_id, $queue_line, $message_id)
    {
        $conf_data  = $queue_line["conf_data"];
        $content    = $queue_line["content"];

        // 訊息目錄（messages/polling/{project_slug}/{message_id}——檔案寫入前必須存在；目錄制）
        $message_dir = sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id);

        sc_mkdir($message_dir);

        // 初始狀態先寫（事件驅動——QUERY_ACCEPTED → queued）
        sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $message_id, "QUERY_ACCEPTED");

        // 活動列起步 log
        sc_ai_chats()->poll()->log()->append_log($chat_id, $message_id, "query started", ["event" => "QUERY_ACCEPTED"]);

        // prompt 檔（使用者問題——worker 讀取）
        sc_ai_chats()->prompt()->set_polling_msg_user_prompt($chat_id, $message_id, $content);

        // 專案規則複製（專案 root AGENTS.md → 訊息資料夾——每訊息一份，零共享零衝突；專案無檔＝略過）
        $project_agents_file = $conf_data["project_path"] . "/AGENTS.md";
        $message_agents_file = $message_dir . "/AGENTS.md";

        // 判定：專案 root AGENTS.md 存在 → 複製一份入訊息資料夾
        if (is_file($project_agents_file)) {
            @copy($project_agents_file, $message_agents_file);
        }

        // conf 檔（daemon／poll 單一讀取來源——掃描開關：最後寫，一見即齊）
        $conf_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "conf");

        // daemon 引擎蓋章（agent 分流——落地嗰刻揀嘅引擎；兩 daemon 並存各自接自己嗰批）
        $conf_data["daemon_engine"] = hkai__core()->get_daemon_engine();

        $conf_data["message_id"] = $message_id;

        sc_write_json($conf_file, $conf_data);
    }

}
