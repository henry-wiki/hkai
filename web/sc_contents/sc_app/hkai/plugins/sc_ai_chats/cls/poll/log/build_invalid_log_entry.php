<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\log;

// == sc_ai_chats poll\log build_invalid_log_entry：log 子域動作（trait——直接合成入 poll_Class（log 子域）；檔＝動作） ==
// == 2026-09-27 自 cls/poll/build_invalid_log_entry.php 搬入（log 子域） ==

trait build_invalid_log_entry
{
    // 異常行 entry（安全防專用——獨立，唔混入正常流程）
    private function build_invalid_log_entry($trimmed_log_line, $reason)
    {
        return [
            "level"     => "error",
            "state"     => "err_server",
            "message"   => $reason . ": " . mb_substr($trimmed_log_line, 0, 200),
            "css_class" => "activity_state_err_server",
        ];
    }
}
