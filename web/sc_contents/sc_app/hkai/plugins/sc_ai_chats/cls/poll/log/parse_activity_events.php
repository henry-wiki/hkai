<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\log;

// == sc_ai_chats poll\log parse_activity_events：log 子域動作（trait——直接合成入 poll_Class（log 子域）；檔＝動作） ==
// == 2026-09-27 自 cls/poll/parse_activity_events.php 搬入（log 子域） ==

trait parse_activity_events
{

    // 活動事件解析（自己讀 msg_log——逐行 JSONL → 顯示事件；不存在／讀唔到 → 空）
    public function parse_activity_events($chat_id, $message_id)
    {
        // 讀 msg_log 全文（@ 抑制＋false → 空——檔案未建立＝冇事件）
        $log_file = sc_ai_chats()->poll()->get_msg_log_file($chat_id, $message_id);

        // 判定：polling 位置冇（訊息已歸檔）→ 改用歸檔目錄同一個檔名
        if (!is_file($log_file)) {
            $archived_message_dir = sc_ai_chats()->poll()->get_archived_poll_dir($chat_id, $message_id);

            $log_file = $archived_message_dir . "/" . basename($log_file);
        }

        $log_raw = @file_get_contents($log_file);
        if ($log_raw === false) {
            $log_raw = "";
        }

        $logs = [];

        // 拆線落變數（跨 OS：\r\n／\r／\n 一律——先拆、後迴圈，中間可隨時 print 做 debug）
        $log_lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $log_raw));

        // 逐行解析（只認 JSONL——每行必須是完整 JSON 物件）
        foreach ($log_lines as $log_line_item) {
            $trimmed_log_line = trim($log_line_item);
            if ($trimmed_log_line === "") {
                continue;
            }
            
            $json_event = json_decode($trimmed_log_line, true);

            // ── 安全防（異常＝嚴重問題，逐項獨立勾出——正常 log 唔會經過） ──
            // 判定：唔係 JSON 物件（decode 失敗＝null／非物件）→ 異常
            if (!is_array($json_event)) {
                $logs[] = sc_ai_chats()->poll()->log()->build_invalid_log_entry($trimmed_log_line, "not a json object");
                continue;
            }

            // 判定：缺 event → 異常
            if (!isset($json_event["event"])) {
                $logs[] = sc_ai_chats()->poll()->log()->build_invalid_log_entry($trimmed_log_line, "missing event");
                continue;
            }

            // 判定：缺 message → 異常
            if (!isset($json_event["message"])) {
                $logs[] = sc_ai_chats()->poll()->log()->build_invalid_log_entry($trimmed_log_line, "missing message");
                continue;
            }

            // 判定：message 係空字串 → 異常
            if ((string)$json_event["message"] === "") {
                $logs[] = sc_ai_chats()->poll()->log()->build_invalid_log_entry($trimmed_log_line, "empty message");
                continue;
            }

            // ── 正常流程（安全防全部過咗先到呢度） ──
            $log_level = isset($json_event["level"]) ? $json_event["level"] : "INFO";
            $log_state = sc_ai_chats()->fsm()->get_display_state($json_event["event"]);

            $log_datetime   = (string)($json_event["datetime"] ?? "");
            $log_event      = (string)$json_event["event"];
            $log_level_text = strtolower($log_level);
            $log_message    = (string)$json_event["message"];

            // 活動 uid＝time＋event＋status＋msg 嘅 md5（同一條記錄穩定識別）
            $log_activity_uid = md5($log_datetime . "|" . $log_event . "|" . $log_level_text . "|" . $log_message);

            $logs[] = [
                "activity_uid" => $log_activity_uid,
                "datetime"     => $log_datetime,
                "event"        => $log_event,
                "level"        => $log_level_text,
                "state"        => $log_state,
                "message"      => $log_message,
                "css_class"    => $log_state === "" ? "" : "activity_state_" . $log_state,
            ];
        }
        
        return $logs;
    }
}
