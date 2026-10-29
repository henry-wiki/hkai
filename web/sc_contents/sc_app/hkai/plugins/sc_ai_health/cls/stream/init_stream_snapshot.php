<?php

namespace sc\app\hkai\plugins\sc_ai_health\stream;

// == sc_ai_health health stream init_stream_snapshot：頁面渲染時記錄當刻狀態（trait——掛入 stream_Class） ==
// == 上游：health_stream.php（每 tick）；下游：monitor()->fetch_target_entries＋stream 讀寫方法 ==
// == 行為：每次呼叫都寫（零比對）：resolve 三份資料 → rev = 舊 + 1 → 寫 stream.json＋chats/{chat_sid}.json，回傳該份 targets ==

trait init_stream_snapshot
{
    // 初始化＋寫當刻快照（頁面入口／URL 變化呼叫；project／chat 未帶＝由請求路徑取）
    public function init_stream_snapshot($request_project_slug = "", $request_chat_sid = "")
    {
        // ① 情境（當前專案／對話——request 端點由參數帶；頁面入口由請求路徑取）
        $request_path  = sc_request()->path();
        $path_segments = explode("/", trim($request_path, "/"));

        $is_request_endpoint = (strpos($request_path, "/request/") === 0);

        $raw_project_slug = $request_project_slug;
        $raw_chat_sid     = $request_chat_sid;

        // 判定：頁面入口且冇帶 → 由請求路徑取（request 端點唔取——/request/hkai/… 唔係對話）
        if ($raw_project_slug === "" && $is_request_endpoint === false) {
            $raw_project_slug = $path_segments[0] ?? "";
        }

        // 判定：頁面入口且冇帶 → 由請求路徑取（request 端點唔取——/request/hkai/… 唔係對話）
        if ($raw_chat_sid === "" && $is_request_endpoint === false) {
            $raw_chat_sid = $path_segments[1] ?? "";
        }

        $resolved_project = sc_ai_projects()->project()->resolve_project_slug($raw_project_slug);
        $resolved_chat    = sc_ai_chats()->chat()->resolve_chat_sid($raw_chat_sid);

        $project_slug = $resolved_project["project_slug"];
        $chat_sid     = $resolved_chat["chat_sid"];

        // ② 情境（傳入各監視對象——git 只計當前專案）
        $monitor_context = [];
        $monitor_context["project_slug"] = $project_slug;
        $monitor_context["chat_sid"]     = $chat_sid;

        $stream_snapshot = sc_ai_health()->stream()->read_stream_snapshot();

        // ③ 逐目標：gate 冇變 → 沿用快照 entries（零 DB、零掃描）；gate 有變／未存 → 才詳細取
        $monitor_entries = [];

        $gated_target_names = ["prompt", "chat", "project"];

        foreach ($gated_target_names as $target_name) {
            $gate_at       = sc_ai_health()->stream()->read_update_gate($target_name);
            $saved_target  = $stream_snapshot[$target_name] ?? null;
            $saved_gate_at = (int)($saved_target["gate_at"] ?? -1);

            // 判定：閘未變且有舊 entries → 沿用（唔查數據庫）
            if ($saved_target !== null && $gate_at === $saved_gate_at) {
                $monitor_entries[$target_name] = $saved_target["entries"];
                continue;
            }

            $monitor_entries[$target_name] = sc_ai_health()->monitor()->fetch_target_entries($target_name, $monitor_context);
        }

        // git：內部每秒 stat 指紋（有變才 spawn git status）——唔經 gate
        $monitor_entries["git"]  = sc_ai_health()->monitor()->fetch_target_entries("git", $monitor_context);

        // conn：讀心跳檔（極輕，唔經 gate）——每 tick 照取
        $monitor_entries["conn"] = sc_ai_health()->monitor()->fetch_target_entries("conn", $monitor_context);

        $stream_targets = sc_ai_health()->stream()->build_stream_targets($monitor_entries, $stream_snapshot);

        // ④ gate 值寫入各目標（下次據此判斷是否需要詳細取）
        foreach ($gated_target_names as $target_name) {
            $stream_targets[$target_name]["gate_at"] = sc_ai_health()->stream()->read_update_gate($target_name);
        }

        // 判定：與存檔完全相同 → 唔重寫（updated_at 不動）
        if (sc_ai_health()->stream()->is_targets_same($stream_targets, $stream_snapshot) === false) {
            sc_ai_health()->stream()->write_stream_snapshot($stream_targets);
        }

        // ④ 當前對話訊息（有 chat_sid 才寫）

        // 判定：冇對話 → 唔寫 chats 檔
        if ($chat_sid === "") {
            return $stream_targets;
        }

        $message_entries   = sc_ai_health()->monitor()->message()->fetch_chat_entries($project_slug, $chat_sid);
        $chat_snapshot = sc_ai_health()->stream()->read_chat_snapshot($chat_sid);

        $chat_target               = [];
        $chat_target["rev"]        = (int)($chat_snapshot["rev"] ?? 0) + 1;
        $chat_target["lastupdate"] = date("Y-m-d H:i:s");
        $chat_target["entries"]    = $message_entries;
        $chat_target["chat_sid"]   = $chat_sid;

        sc_ai_health()->stream()->write_chat_snapshot_raw($chat_sid, $chat_target);

        return $stream_targets;
    }
}
