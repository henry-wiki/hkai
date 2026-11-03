<?php

namespace sc\app\hkai\plugins\sc_stream\sse\sse_prompt;

// == sse_prompt_Class：草稿串流主迴圈（singleton——經 sc_stream()->sse_prompt() 取得） ==
// == 職責：每 0.1 秒跑 get_snapshot_changes_for_prompt()，有變才推 prompt event；ping 保活；逾時／斷線收工 ==
// == 上游：plugins/sc_stream/request/prompt_stream.php（route）；下游：sse()->*、monitor()->prompt()->* ==

class sse_prompt_Class
{
    // 單條串流最長秒數（略低於 FcgidBusyTimeout 300——到時主動結束，前端自動重連）
    private const MAX_SECONDS = 280;

    // 每輪等待（微秒——0.1 秒）
    private const TICK_USEC = 100000;

    // 無變化時每 15 秒送一個 comment（保活）
    private const PING_SEC = 15;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct()
    {}

    // 草稿串流主迴圈
    public function run_prompt_stream()
    {
        session_write_close();
        @ini_set("zlib.output_compression", "0");
        @ini_set("implicit_flush", "1");
        set_time_limit(0);

        sc_stream()->sse()->open_stream();

        $view_id = sc_stream()->get_view_id();

        $stream_start   = time();
        $last_ping_time = time();

        while (true) {
            // 判定：客戶端已斷 → 結束
            if (connection_aborted()) {
                break;
            }

            // 判定：到時間上限 → 結束（前端 EventSource 會自動重連）
            if (time() - $stream_start >= self::MAX_SECONDS) {
                break;
            }

            $stream_events = $this->get_snapshot_changes_for_prompt($view_id);

            // 判定：有事件 → 逐個推
            if ($stream_events !== null) {
                foreach ($stream_events as $event_name => $event_payload) {
                    sc_stream()->sse()->send_event($event_name, $event_payload);
                }

                $last_ping_time = time();
            } else {
                // 判定：長時間無變化 → 送 comment 保活
                if (time() - $last_ping_time >= self::PING_SEC) {
                    sc_stream()->sse()->send_ping();

                    $last_ping_time = time();
                }
            }

            // 清逾時 view 檔（函數自行 60 秒節流——每 tick 叫即可）
            sc_stream()->snapshot()->sweep_expired_view_files();

            // 清舊歸檔目錄／舊備份檔（函數自行 60 秒節流——每 tick 叫即可）
            sc_stream()->snapshot()->sweep_old_archive_and_backup_files();

            usleep(self::TICK_USEC);
        }

        sc_stream()->sse()->send_close();
    }

    // 跑一輪（prompt 線）：取當刻草稿 → 寫快照 → 比本頁已見 → 有變回 event
    public function get_snapshot_changes_for_prompt($view_id)
    {
        // 骨架（同 state 版 100% 相同：changed／added／updated／deleted）
        $snapshot_changes = [];
        $snapshot_changes["prompt"] = ["changed" => 0, "added" => [], "updated" => [], "deleted" => []];

        // 步驟①：刷新快照（取當刻草稿＋寫快照）
        sc_stream()->snapshot()->prompt()->refresh_snapshot();

        // 步驟②：讀刷新後最新快照
        $live_snapshot_states = sc_stream()->snapshot()->prompt()->get_live_snapshot_states();

        // 步驟③：讀本頁已見版本（<target>/view/{view_id}.json）
        $view_snapshot_states = sc_stream()->snapshot()->prompt()->get_view_snapshot_states($view_id);

        // 步驟④：直接比對「當刻 prompt」與「本頁已見」
        $target_changes   = sc_stream()->snapshot()->get_snapshot_diff($live_snapshot_states, $view_snapshot_states);
        $snapshot_changes = array_replace($snapshot_changes, $target_changes);

        // 同步本頁已見版本（每 tick 更新；下一 tick 只推真正有變嘅）
        sc_stream()->snapshot()->prompt()->sync_snapshot_views($view_id);

        $changed_events = [];

        // 逐個 target 篩出有變（同 state 版同一出口寫法）
        foreach ($snapshot_changes as $target_name => $target_change_item) {
            // 判定：此 target 冇變 → 跳過
            if ((int)$target_change_item["changed"] !== 1) {
                continue;
            }

            $changed_events[$target_name] = $target_change_item;
        }

        // 判定：冇任何變 → null
        if (count($changed_events) < 1) {
            return null;
        }

        return $changed_events;
    }
}
