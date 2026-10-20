<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm\fsm_Class（bridge 檔）：狀態機本體（單一詞彙：status——event → status 解析；落地由 status class 負責） ==
// == SSOT：web/sc_config/hkai/fsm_map.json → status／event_to_status／transitions ==
// == 動作（cls/fsm/ 各 trait——一檔一動作）：resolve_status_by_event／is_status_event／get_display_state／is_terminal_status ==
// == 2026-09-27 方法拆入 cls/fsm/（one file one function）；屬性＋constructor 留本體（狀態載入） ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class fsm_Class
{
    use resolve_status_by_event;
    use is_status_event;
    use get_display_state;
    use is_terminal_status;
    use get_terminal_status_list;

    private static $instance = null;

    // 有轉移定義的事件集合（＝會驅動 status——供 log 分類 status／trace）
    private $status_events = [];

    // 事件 → status（fsm_map event_to_status——event 唯一決定）
    private $event_to_status = [];

    // event → 顯示 state（活動列顏色分組；fsm_map event_to_display_state——未列＝""）
    private $event_to_display_state = [];

    // 終態集合（fsm_map status.terminal——完成／失敗／取消）
    private $terminal_status = [];

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // 載入 fsm_map（SSOT——路徑由 __init__.php 入口注入；class 零寫死）
        $fsm_map_path = hkai__core()->get_fsm_map_file();
        $fsm_map      = json_decode(@file_get_contents($fsm_map_path), true);

        // 事件 → status（fsm_map 對應表——event 唯一決定）
        $this->event_to_status = $fsm_map["event_to_status"] ?? [];

        // event → 顯示 state（活動列顏色分組——fsm_map 唯一源）
        $this->event_to_display_state = $fsm_map["event_to_display_state"] ?? [];

        // 終態集合（fsm_map 唯一源——唔手抄）
        $this->terminal_status = $fsm_map["status"]["terminal"] ?? [];

        // 逐條登錄事件（供 is_status_event——有轉移定義即會驅動 status）
        foreach (($fsm_map["transitions"] ?? []) as $transition) {
            $this->status_events[$transition["event"]] = true;
        }
    }

}
