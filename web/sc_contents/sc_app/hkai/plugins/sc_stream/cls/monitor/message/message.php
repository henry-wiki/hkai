<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\message;

// == message_Class：message 監視對象（singleton——只做組裝） ==
// == 上游：monitor_Class 清單；下游：fetch_source_entries／get_live_snapshot／write_snapshot／sync_snapshot_to_view／get_snapshot_diff ==

class message_Class
{
    use fetch_source_entries;
    use get_live_snapshot;
    use write_snapshot;
    use sync_snapshot_to_view;
    use get_snapshot_diff;

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

    // 此刻可否初始化
    public function is_ready()
    {
        $chat_sid = sc_stream()->resolve_chat_sid();

        // 判定：冇對話 → 唔初始化
        if ($chat_sid === "") {
            return false;
        }

        return true;
    }

    // 初始化：有 chat_sid 才做；取 entries → 寫訊息快照
    public function init_snapshot()
    {
        // 判定：此 target 此刻唔需要初始化 → 唔做
        if ($this->is_ready() === false) {
            return;
        }

        $live_entries = $this->fetch_source_entries();

        sc_stream()->monitor()->message()->write_snapshot($live_entries);
    }

}
