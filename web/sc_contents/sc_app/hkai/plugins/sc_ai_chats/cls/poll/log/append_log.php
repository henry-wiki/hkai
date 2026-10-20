<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\log;

// == sc_ai_chats poll\log append_log：log 子域動作（trait——直接合成入 poll_Class（log 子域）；檔＝動作） ==
// == 2026-09-27 自 cls/poll/append_log.php 搬入（log 子域） ==

trait append_log
{
    // 標準寫入一條 log（JSONL 固定外殼——組行＋append msg_log.txt（目錄制））
    // 外殼：{datetime, source, kind, level, event, message, [metadata], [payload]}——metadata/payload 有值才寫；chat_id/message_id 由檔名承載
    // kind：state（有轉移定義——會驅動狀態）／trace（純足跡）
    // 參數：核心 chat_id/message_id 用於檔名定位；level/event/metadata/payload 收進 $context（level 預設 INFO）
    public function append_log($chat_id, $message_id, $message, $context = [])
    {
        // 事件分類：state（有轉移定義——會驅動狀態）／trace（純足跡）
        $event_name = $context["event"] ?? "";
        $event_kind = sc_ai_chats()->fsm()->is_status_event($event_name) ? "state" : "trace";
        
        // 直接在陣列中一氣呵成組裝，並使用 ?? 簡化預設值邏輯
        $log_line_data = [
            "datetime"   => date("Y-m-d H:i:s"),
            "source"     => "php-api",
            "kind"       => $event_kind,
            "level"      => $context["level"] ?? "INFO",
            "event"      => $event_name,
            "message"    => $message,
        ];
        // metadata 有值才寫（空白省略）
        if (!empty($context["metadata"])) {
            $log_line_data["metadata"] = $context["metadata"];
        }
        // payload 有值才寫（預留——資料本體：請求/回應摘要）
        if (!empty($context["payload"])) {
            $log_line_data["payload"] = $context["payload"];
        }
        // meta 有值才寫（附加數據——目前無消費端；與 metadata 用途分家）
        if (!empty($context["meta"])) {
            $log_line_data["meta"] = $context["meta"];
        }
        
        // 追加一行（JSONL 標準 \n 行尾——寫入交 helper）
        sc_append_jsonl(sc_ai_chats()->poll()->get_msg_log_file($chat_id, $message_id), $log_line_data);
    }
}
