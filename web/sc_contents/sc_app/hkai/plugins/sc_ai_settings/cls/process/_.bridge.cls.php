<?php

namespace sc\app\hkai\plugins\sc_ai_settings\process;

// == sc_ai_settings process：process 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->process()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat main_Class＋fetch_Class 合成） ==
// == 職責：Process Viewer 分頁（畫面語彙：Cyber——等寬字 ＋ 霓虹點綴；樣式見 asset/sc_ai_settings.css 的 .process_viewer_*） ==
// == 程序資料一律經 hkai__python_bridge_process()（Python 側唯一入口） ==

class process_Class
{
    use view\handle_fetch_process_viewer;
    use action\handle_kill_process;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct()
    {}

    // 這一頁的內容（template ＋ slots——插入 .modal__content；一次從頭建到尾）
    private function get_process_viewer_content()
    {
        $process_data   = hkai__python_bridge_process()->get_process_snapshot();
        $live_processes = $process_data["live_processes"];
        $process_counts = $process_data["process_counts"];
        $daemon_started = $process_data["daemon_started_fmt"];
        $alive_count    = $process_data["alive_count"];

        // 摘要狀態（有任一存活程序 → alive；否則 idle）
        $state_class = "idle";
        $state_label = "IDLE";
        if ($alive_count > 0) {
            $state_class = "alive";
            $state_label = "RUNNING";
        }

        // === header ===
        $modal_header = [];
        $modal_header[] = "<div class='modal__title'>Process Viewer</div>";
        $modal_header[] = "<div class='modal__subtitle'>Live processes (heartbeat files).</div>";

        // === footer ===
        $modal_footer = [];
        $modal_footer[] = "<div class='modal__content-footer'>";
            $modal_footer[] = "<button type='button' class='button button--secondary' data-btn='close_modal'>Close</button>";
        $modal_footer[] = "</div>";

        // === body 骨架（固定——動態值一律由 slots 填入 data-slot 容器） ===
        $modal_body_lines = [];
        $modal_body_lines[] = "<div class='modal__content-main process_viewer'>";
            $modal_body_lines[] = "<div class='process_viewer_summary' data-slot='summary'></div>";
            $modal_body_lines[] = "<div class='process_viewer_block'>";
                $modal_body_lines[] = "<div class='process_viewer_block_title'>";
                    $modal_body_lines[] = "<div class='process_viewer_block_title_left'>ACTIVE PROCESSES</div>";
                $modal_body_lines[] = "</div>";
                $modal_body_lines[] = "<div class='process_viewer_rows' data-slot='rows'></div>";
            $modal_body_lines[] = "</div>";
        $modal_body_lines[] = "</div>";

        $modal_body_skeleton = implode("", $modal_body_lines);

        // --- 摘要列（存活總數 ＋ 各層計數 ＋ daemon 啟動時間）→ 單一來源 $summary_html ---
        $summary_lines = [];
        $summary_lines[] = "<span class='process_viewer_state process_viewer_state--" . $state_class . "'>" . $state_label . " " . $alive_count . "</span>";

        // 逐層計數（固定順序：bridge → aider_daemon → worker → aider）
        foreach ($process_counts as $process_name => $process_count_item) {
            $summary_lines[] = "<span class='process_viewer_count'><em>" . sc_html_encode($process_name) . "</em><b>" . (int)$process_count_item . "</b></span>";
        }

        // 判定：daemon 啟動時間已知 → 顯示（since）
        if ($daemon_started !== "") {
            $summary_lines[] = "<span class='process_viewer_since'>since " . sc_html_encode($daemon_started) . "</span>";
        }

        // data-slot='summary'：前端靜默更新時只換這塊（外層不動）
        $summary_html = "<div class='process_viewer_summary' data-slot='summary'>" . implode("", $summary_lines) . "</div>";

        // --- 活躍程序（狀態檔心跳 ＋ PHP 以 PID 現查；死檔已被清掉，故只列活躍者） ---
        // 資料列 → 單一來源 $rows_html（data-slot='rows'：前端靜默更新時只換這塊）
        $rows_lines = [];

        // 判定：無任何程序 → 空狀態
        if (count($live_processes) === 0) {
            $rows_lines[] = "<div class='process_viewer_empty'>no active process</div>";
        }

        $live_processes = sc_html_encode($live_processes);
        // 逐筆渲染（鏈序 → pid——行內組裝：本檔唯一一處列渲染，不需共用函數）
        foreach ($live_processes as $live_process_item) {
            $live_pid     = (int)$live_process_item["pid"];
            $live_program = (string)$live_process_item["program"];
            $extra_text   = (string)$live_process_item["extra_text"];

            $rows_lines[] = "<div class='process_viewer_row'>";
                $rows_lines[] = "<span class='process_viewer_dot process_viewer_dot--alive'></span>";
                $rows_lines[] = "<span class='process_viewer_program' title='" . $live_program . "'>" . $live_program . " (" . $live_pid . ")</span>";
                $rows_lines[] = "<span class='process_viewer_extra'>" . $extra_text . "</span>";
                $kill_attr = [];
                $kill_attr[] = "type='button'";
                $kill_attr[] = "class='process_viewer_kill'";
                $kill_attr[] = "data-process_kill='{$live_pid}'";
                $kill_attr[] = "data-process_program='{$live_program}'";
                $kill_attr[] = "data-confirm_msg='" . hkai__i18n()->get("settings.process.kill_confirm") . "'";
                $kill_attr[] = "title='" . hkai__i18n()->get("settings.process.kill_title") . "'";

                $kill_attr = implode(" ", $kill_attr);
                $rows_lines[] = "<button {$kill_attr}>KILL</button>";
            $rows_lines[] = "</div>";
        }

        $rows_html = "<div class='process_viewer_rows' data-slot='rows'>" . implode("", $rows_lines) . "</div>";

        $modal = [
            // 固定外殼（template）：標題列 ＋ 內容骨架（slot 佔位）＋ 頁腳——同一分頁每次都一樣
            "template" => [
                "header" => implode("", $modal_header),
                "body"   => $modal_body_skeleton,
                "footer" => implode("", $modal_footer),
            ],

            // 動態內容（slots）：每個 key 對應 template 內同名 data-slot 的容器
            "slots" => [
                "summary" => $summary_html,
                "rows"    => $rows_html,
            ],
        ];

        return $modal;
    }
}
