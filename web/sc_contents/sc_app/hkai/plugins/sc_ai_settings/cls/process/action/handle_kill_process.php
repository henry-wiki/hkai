<?php

namespace sc\app\hkai\plugins\sc_ai_settings\process\action;

// == sc_ai_settings process handle_kill_process：process 域動作（trait——掛入 process_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat main_Class 分遷入（one file one action） ==

trait handle_kill_process
{
    // POST kill_process handler（終止一個「本系統程序」——白名單：心跳檔認名）
    public function handle_kill_process()
    {
        $request_data = sc_request()->json();
        $process_pid  = (int)($request_data["pid"] ?? 0);

        // 判定：不在白名單（process_status 無此 pid 之心跳檔）→ 拒絕（不執行任何終止）
        if (!hkai__python_bridge_process()->has_status_heartbeat($process_pid)) {
            return sc_response()->json(["ok" => false, "message" => "process not found: " . $process_pid]);
        }

        $killed = hkai__python_bridge_process()->kill_process($process_pid);

        $message = $killed ? "killed " . $process_pid : "kill failed: " . $process_pid;
        return sc_response()->json(["ok" => $killed, "message" => $message, "pid" => $process_pid]);
    }
}
