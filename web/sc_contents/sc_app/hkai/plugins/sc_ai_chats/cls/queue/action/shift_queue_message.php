<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue shift_queue_message：queue 域動作（trait——掛入 queue_Class；檔＝動作：抽隊頭進場（寫 DB＋建檔＋出列＋通知 daemon）） ==
// == 2026-09-27 自 prompt/queue.php 分獨立域（檔＝動作） ==

trait shift_queue_message
{

    //---------------------------------------------------------
    // 1. 對話忙（仲有目錄）＝唔抽
    // 2. 抽排隊第一條
    // 3. 寫入 DB（變成真訊息）
    // 4. 建檔＋出列（唔再排隊）
    // 5. 通知 daemon 去做
    //---------------------------------------------------------
    // 回傳＝["message_row" => …, "queue_uid" => …]（唔空閒／冇隊列／DB 寫入失敗 = null）
    public function shift_queue_message($chat_id)
    {
        // 對話內任務（活躍區訊息——完成即歸檔；有任務＝有嘢做緊）
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs($chat_id);
        
        // 判定：有任務 → 對話正忙，唔放住
        if (count($polling_jobs) > 0) {
            return null;
        }
        
        // 等待列（queue 檔——首條＝FIFO 隊頭）
        $queue_lines = sc_ai_chats()->queue()->get_queue_lines($chat_id);
        
        // 判定：冇等待中訊息 → 唔動作
        if (count($queue_lines) < 1) {
            return null;
        }
        
        $queue_line = $queue_lines[0];
        $conf_data  = $queue_line["conf_data"];
        
        // 進場：insert DB 訊息列（message_id 由此出——內容＋模型資訊全由 queue 行帶入）
        $model_pair = explode("/", $conf_data["model_name"], 2);
        
        $message_model_info = [];
        $message_model_info["chat_mode"]   = $conf_data["chat_mode"];
        $message_model_info["provider_id"] = $model_pair[0];
        $message_model_info["model_id"]    = isset($model_pair[1]) ? $model_pair[1] : "";
        
        $project_id     = (int)$conf_data["project_id"];
        $content        = (string)$queue_line["content"];
        $message_row    = sc_ai_chats()->message()->repo_update()->insert_db_message($project_id, $chat_id, $content, $message_model_info);
        
        // 判定：DB 寫入失敗 → 記錯誤 log＋唔出列（原行留隊，下一拍再試）
        if ($message_row === null) {
            hkai__sc_runtime_io()->error("shift_queue_message insert failed: chat_id=" . $chat_id . " queue_uid=" . $queue_line["queue_uid"]);

            return null;
        }
        
        $message_id = (int)$message_row["message_id"];
        
        // 建檔（queue 行 → conf／狀態／log／prompt——conf 最後寫＝掃描開關）
        sc_ai_chats()->queue()->materialize_queue_message($chat_id, $queue_line, $message_id);
        
        // 出等待列（按 queue_uid——行存在＝未派工專屬；已派工唔應該再顯示 chip）
        sc_ai_chats()->queue()->remove_queue_line($chat_id, $queue_line["queue_uid"]);
        
        // 放出去（notify bridge——喚醒與否由 bridge 決定；daemon 最舊優先＝就係呢條）
        hkai__python_bridge_daemon()->notify_daemon_add_message($chat_id, $message_id);

        // 專案統計（最後一步——唔影響派工）
        sc_ai_projects()->project()->refresh_project_stats($project_id);
        
        $shift_result = [];
        $shift_result["message_row"] = $message_row;
        $shift_result["queue_uid"]   = $queue_line["queue_uid"];
        
        return $shift_result;
    }
}
