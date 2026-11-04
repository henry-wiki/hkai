<?php

namespace sc\app\hkai\plugins\sc_stream\monitor\prompt;

// == prompt_Class：prompt 監視對象（singleton——只做組裝） ==
// == 上游：monitor_Class 清單、sse_prompt；下游：fetch_source_entries／get_live_snapshot／write_live_snapshot／get_snapshot_diff／sync_snapshot_to_view ==

class prompt_Class
{
    use fetch_source_entries;
    use get_live_snapshot;
    use write_live_snapshot;
    use get_snapshot_diff;
    use sync_snapshot_to_view;

    private static $instance = null;
    // 重推次數（1 tick ＝ 1 秒；null ＝ 唔重推）
    private $repeat_tick_count = null;

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
        return true;
    }

    // 初始化：取 entries → 寫快照
    public function init_snapshot()
    {
        // 判定：此 target 此刻唔需要初始化 → 唔做
        if ($this->is_ready() === false) {
            return;
        }

        $live_entries = $this->fetch_source_entries();

        $this->write_live_snapshot($live_entries);
    }
}
