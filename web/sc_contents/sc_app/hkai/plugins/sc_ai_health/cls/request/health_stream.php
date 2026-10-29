<?php

namespace sc\app\hkai\plugins\sc_ai_health\request;

// == sc_ai_health health health_stream：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 職責：SSE 變化監視（後端 diff）——每個監視對象各自一個記錄（rev／lastupdate／entries） ==
// == 上游：request/health/health_stream.php（route）；下游：stream()->init_stream_snapshot、viewers/{id}.json、chats/{chat_sid}.json ==
// == 契約：event=status；data={"changed":[...],"<項>":{"rev":n,"lastupdate":"…","changes":{…}},"viewer_id":"…"} ==

trait health_stream
{
    // 單條串流最長秒數（略低於 FcgidBusyTimeout 300——到時主動結束，前端自動重連）
    private const SSE_MAX_SECONDS = 280;

    // 每秒檢查一次（微秒級 stat；唔跑 git）
    private const SSE_TICK_SEC = 1;

    // 無變化時每 15 秒送一個 comment（保活）
    private const SSE_PING_SEC = 15;

    // 首幀 handshake 塞滿量（bytes——沖走下層 4KB 緩衝）
    private const SSE_HANDSHAKE_PAD_BYTES = 4096;

    // SSE 主迴圈（有變才推 status event；結束由時間上限或客戶端中斷）
    public function health_stream()
    {
        session_write_close();
        set_time_limit(0);

        sc_ai_health()->request()->send_sse_headers();
        sc_ai_health()->request()->flush_all_output_buffers();

        // 首幀 handshake（塞滿下層 4KB 緩衝——唔塞，單一 status 會被扣住唔出）
        echo "event: open\ndata: " . json_encode(["ok" => true, "padding" => str_repeat("-", self::SSE_HANDSHAKE_PAD_BYTES)], JSON_UNESCAPED_SLASHES) . "\n\n";

        sc_ai_health()->request()->flush_sse_output();

        $viewer_id = sc_ai_health()->request()->read_stream_viewer_id();

        $project_slug = sc_ai_health()->request()->read_stream_project_slug();
        $chat_sid     = sc_ai_health()->request()->read_stream_chat_sid();

        $stream_start   = time();
        $last_ping_time = time();
        $last_sweep_time = time();
        $last_finalize_time = time();

        while (true) {
            // 判定：客戶端已斷 → 結束
            if (connection_aborted()) {
                break;
            }

            // 判定：到時間上限 → 結束（前端 EventSource 會自動重連）
            if (time() - $stream_start >= self::SSE_MAX_SECONDS) {
                break;
            }

            // ① 取本頁所有變更（頁面＋對話訊息——比對統一在 stream 域）
            $stream_events = sc_ai_health()->stream()->get_stream_events($viewer_id, $project_slug, $chat_sid);

            // 判定：有變化 → 逐個推
            if (count($stream_events) > 0) {
                foreach ($stream_events as $stream_event) {
                    $stream_event["viewer_id"] = $viewer_id;

                    $change_json = json_encode($stream_event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    echo "event: status\n";
                    echo "data: " . $change_json . "\n\n";

                    sc_ai_health()->request()->flush_sse_output();
                }

                $last_ping_time = time();
            } else {
                // 判定：長時間無變化 → 送 comment 保活
                if (time() - $last_ping_time >= self::SSE_PING_SEC) {
                    echo ": ping\n\n";

                    sc_ai_health()->request()->flush_sse_output();

                    // ping 同時保活（viewer 檔 mtime＝連線心跳；逾 5 分鐘＝冇人用）
                    sc_ai_health()->stream()->refresh_viewer_timestamp($viewer_id);

                    $last_ping_time = time();
                }
            }


            // 判定：每 5 秒掃一次輪詢區終態 → 收尾（message_poll 已唔再由前端觸發）
            if (time() - $last_finalize_time >= 5) {
                sc_ai_chats()->poll()->finalize_terminal_jobs();

                $last_finalize_time = time();
            }

            // 判定：每 60 秒清一次逾時 viewer 檔（唔靠開頁觸發）
            if (time() - $last_sweep_time >= 60) {
                sc_ai_health()->stream()->sweep_expired_viewer_files();

                $last_sweep_time = time();
            }

            sleep(self::SSE_TICK_SEC);
        }

        echo "event: bye\n";
        echo "data: {}\n\n";

        sc_ai_health()->request()->flush_sse_output();
    }

    // 讀 viewer_id（GET query——本頁參照檔 id；只收 32 hex，其餘當冇）
    public function read_stream_viewer_id()
    {
        $query_data = sc_request()->query();
        $viewer_id  = isset($query_data["viewer_id"]) ? (string)$query_data["viewer_id"] : "";

        // 判定：格式唔合法 → 當冇（防止亂寫 viewers 檔）
        if (preg_match("/^[0-9a-f]{32}$/", $viewer_id) !== 1) {
            return "";
        }

        return $viewer_id;
    }

    // 讀 project_slug（GET query——只收安全字元；其餘當冇）
    public function read_stream_project_slug()
    {
        $query_data   = sc_request()->query();
        $project_slug = isset($query_data["project_slug"]) ? (string)$query_data["project_slug"] : "";

        // 判定：格式唔合法 → 當冇
        if (preg_match("/^[A-Za-z0-9_-]{1,64}$/", $project_slug) !== 1) {
            return "";
        }

        return $project_slug;
    }

    // 讀 chat_sid（GET query——只收 32 hex；其餘當冇）
    public function read_stream_chat_sid()
    {
        $query_data = sc_request()->query();
        $chat_sid   = isset($query_data["chat_sid"]) ? (string)$query_data["chat_sid"] : "";

        // 判定：格式唔合法 → 當冇
        if (preg_match("/^[0-9a-f]{32}$/", $chat_sid) !== 1) {
            return "";
        }

        return $chat_sid;
    }










    // SSE headers（唔經框架 response——串流必須直接輸出）
    public function send_sse_headers()
    {
        header("Content-Type: text/event-stream; charset=utf-8");
        header("Cache-Control: no-cache, no-transform");
        header("Connection: keep-alive");
        header("X-Accel-Buffering: no");
    }

    // 清空全部輸出緩衝（框架可能已開緩衝）
    public function flush_all_output_buffers()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    // 推送後即時 flush（逐塊送出）
    public function flush_sse_output()
    {
        // 判定：仍有緩衝 → 先送
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
