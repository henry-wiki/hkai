<?php

namespace sc\app\hkai\plugins\sc_stream\sse\sse_state;

// == sse_state_Class：狀態串流主迴圈（singleton——經 sc_stream()->sse_state() 取得） ==
// == 職責：每秒 diff 一次（get_snapshot_changes_for_state），有變才逐 type 推 event；ping 保活；逾時／斷線收工 ==
// == 上游：plugins/sc_stream/request/state_stream.php（route）；下游：sse()->*、工作函數 ==

class sse_state_Class
{
    // 單條串流最長秒數（略低於 FcgidBusyTimeout 300——到時主動結束，前端自動重連）
    private const MAX_SECONDS = 280;

    // 每輪等待（微秒——1 秒）
    private const TICK_USEC = 1000000;

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

    // 狀態串流主迴圈
    public function run_state_stream()
    {
        session_write_close();
        @ini_set("zlib.output_compression", "0");
        @ini_set("implicit_flush", "1");
        set_time_limit(0);

        sc_stream()->sse()->open_stream();

        $view_id = sc_stream()->get_view_id();

        // 連接身份（本頁一格；冇合法 view_id → 唔登記）
        $sse_owner_file = sc_stream()->sse_owner()->open($view_id, "state_stream");

        // 開線／重連即清本頁已見基準——下一 tick 全量補（防換線窗口漏事件後永遠唔補）
        sc_stream()->snapshot()->state()->reset_view_snapshot_states($view_id);

        $stream_start   = time();
        $last_ping_time = time();

        $is_first_tick = true;

        while (true) {
            // 判定：客戶端已斷 → 結束
            if (connection_aborted()) {
                break;
            }

            // 判定：到時間上限 → 結束（前端 EventSource 會自動重連）
            if (time() - $stream_start >= self::MAX_SECONDS) {
                break;
            }

            // （接管判斷已移除：EventSource 自動重連會造成互殺乒乓——只靠 MAX_SECONDS 換線）


            $stream_events = $this->get_snapshot_changes_for_state($view_id);

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

            $this->run_sweeps($is_first_tick);

            $is_first_tick = false;

            usleep(self::TICK_USEC);
        }

        // 收工：刪自己身份檔（格內冇其它連線 → 連格一齊刪）
        sc_stream()->sse_owner()->close($sse_owner_file);

        sc_stream()->sse()->send_close();
    }

    // 每 tick 三個收尾掃描
    private function run_sweeps($is_first_tick)
    {
        // 清逾時 view 檔（函數自行 60 秒節流——每 tick 叫即可）
        sc_stream()->snapshot()->sweep_expired_view_files();

        // 清舊歸檔目錄／舊備份檔（函數自行 60 秒節流——每 tick 叫即可）
        sc_stream()->snapshot()->sweep_old_archive_and_backup_files();

        // 終態輪詢工作收尾（3 秒節流＋flock 互斥；開線第一 tick 強制掃一次）
        sc_stream()->snapshot()->sweep_terminal_poll_jobs($is_first_tick);
    }
    // -------------------------------------------------------------------------------------------------------------------------
    // 狀態快照比對：刷新快照 → 讀本頁已見 → 逐 target 比對 → 篩有變
    // 上游：run_state_stream()（每 tick）
    // 下游：state()->refresh_snapshot()
    //       state()->get_live_snapshot_states()
    //       state()->get_view_snapshot_states()
    //       snapshot()->get_snapshot_diff()
    //       state()->sync_snapshot_views()
    // 出口：① 無變 → null　② 有變 → 同步本頁版本、回 event
    // -------------------------------------------------------------------------------------------------------------------------
    public function get_snapshot_changes_for_state($view_id)
    {
        $snapshot_changes = [];

        // 步驟①：刷新快照（state 線四個 target——只寫，唔讀）
        sc_stream()->snapshot()->state()->refresh_snapshot();

        // 步驟②：讀刷新後最新快照
        $live_snapshot_states = sc_stream()->snapshot()->state()->get_live_snapshot_states();

        // 步驟③：讀本頁已見版本（<target>/view/{view_id}.json）
        $view_snapshot_states = sc_stream()->snapshot()->state()->get_view_snapshot_states($view_id);


        // 步驟④：直接比對「當刻四個 target」與「本頁已見」
        $target_changes   = sc_stream()->snapshot()->get_snapshot_diff($live_snapshot_states, $view_snapshot_states);

        $snapshot_changes = array_replace($snapshot_changes, $target_changes);


        // 同步本頁已見版本（每 tick 更新；下一 tick 只推真正有變嘅）
        sc_stream()->snapshot()->state()->sync_snapshot_views($view_id);

        $changed_events = [];

        // 逐個 target 篩出有變
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