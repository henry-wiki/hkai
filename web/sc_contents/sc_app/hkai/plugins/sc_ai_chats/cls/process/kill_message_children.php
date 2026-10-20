<?php
// == sc_ai_chats/cls/process/kill_message_children.php：process 域動作（trait——掛入 process_Class；檔＝動作） ==
// == 職責：kill_message_children——終止該訊息嘅回應：搵 worker（find_worker_pids）→ 統一刀 kill_process（worker 連血緣子孫） ==
namespace sc\app\hkai\plugins\sc_ai_chats\process;

trait kill_message_children
{
    // 依賴顯式化（find_worker_pids 嚟自同域 trait；kill_process 嚟自 sc_python_bridge_process——殺嘅統一刀）

    // 終止指定訊息嘅回應（＝殺 worker 樹：worker 連血緣子孫——血緣最深層先殺，統一經 kill_process：bridge → Python）
    // 殺完唔改台帳（死行由 health 清潔工剔——2026-10-08 改向）
    // 回傳 [pid => 是否成功]
    public function kill_message_children($chat_id, $message_id)
    {
        $worker_pids = sc_ai_chats()->process()->find_worker_pids($chat_id, $message_id);
        $result      = [];

        // 逐個 worker → 統一刀（worker 連血緣子孫一齊殺）
        foreach ($worker_pids as $worker_pid_item) {
            $result[$worker_pid_item] = hkai__python_bridge_process()->kill_process($worker_pid_item);
        }

        return $result;
    }
}
