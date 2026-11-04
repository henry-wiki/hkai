<?php

namespace sc\app\hkai\plugins\sc_stream\snapshot;

// == snapshot_Class：快照子域（singleton——經 sc_stream()->snapshot() 取得） ==
// == 職責：逐 target 快照／view／chat 讀寫＋gate 標記（內部呼叫一律 $this） ==
// == 上游：sc_stream_Class（SSE 事件）、sc_app/hkai/index.php（開頁基線）；下游：sc_stream()->monitor()、data/stream/ ==

class snapshot_Class
{
    use \sc\app\hkai\plugins\sc_stream\snapshot\cleanup\cleanup;

    private static $instance = null;

    // 本請求 view id（init_snapshot 時產生）
    private $view_id = "";

    // 單例入口（全 plugin 共用一個 snapshot 物件）
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

    /**
     * state 線刷新（state 串流每 tick 步驟①）
     *
     * @return \sc\app\hkai\plugins\sc_stream\snapshot\state\state_Class
     */
    public function state()
    {
        return \sc\app\hkai\plugins\sc_stream\snapshot\state\state_Class::getInstance();
    }

    /**
     * prompt 線刷新（prompt 串流每 0.1 秒 步驟①）
     *
     * @return \sc\app\hkai\plugins\sc_stream\snapshot\prompt\prompt_Class
     */
    public function prompt()
    {
        return \sc\app\hkai\plugins\sc_stream\snapshot\prompt\prompt_Class::getInstance();
    }

    // 取本請求 view id（init_snapshot 時產生；未產生 → 空字串）
    public function get_view_id()
    {
        return $this->view_id;
    }

    // 本頁 view 檔身份段（同一 view_id 之下，按 target 所屬語境分檔——換對話／換專案唔會撞基準）
    public function get_view_identity($target_name)
    {
        $chat_targets = ["message", "prompt"];

        // 判定：訊息類 → 用對話身份
        if (in_array($target_name, $chat_targets, true) === true) {
            return $this->sanitize_view_identity(sc_stream()->resolve_chat_sid());
        }

        $project_targets = ["chat"];

        // 判定：專案範圍 → 用專案身份
        if (in_array($target_name, $project_targets, true) === true) {
            return $this->sanitize_view_identity(sc_stream()->resolve_project_slug());
        }

        return "";
    }

    // 清身份段（只准英數、_、-；有怪字元 → 當冇身份）
    private function sanitize_view_identity($identity)
    {
        // 判定：空 → 空
        if ($identity === "") {
            return "";
        }

        // 判定：格式唔合法 → 當冇身份
        if (preg_match("/^[A-Za-z0-9_-]+$/", $identity) !== 1) {
            return "";
        }

        return $identity;
    }

    // 計 view 檔路徑：data/stream/<target>/view/<view_id>__<身份>.json（冇身份 → <view_id>.json）
    public function get_snapshot_view_file($target_name, $view_id)
    {
        $stream_dir = sc_get_config("hkai.stream_dir");

        $target_view_dir = $stream_dir . "/" . $target_name . "/view";

        $view_identity = $this->get_view_identity($target_name);

        $view_file_name = $view_id . ".json";

        // 判定：有身份 → 檔名加身份段
        if ($view_identity !== "") {
            $view_file_name = $view_id . "__" . $view_identity . ".json";
        }

        $snapshot_view_file = $target_view_dir . "/" . $view_file_name;

        return $snapshot_view_file;
    }

    // 建立本頁 view id（只建一次；已建立 → 沿用）
    public function create_view_snapshot()
    {
        // 判定：本請求已有 view id → 直接沿用，唔重建
        if ($this->view_id !== "") {
            return $this->view_id;
        }

        $view_id = sc_uuid7();

        $this->view_id = $view_id;

        return $view_id;
    }

    // 比對兩份 states（逐 target）→ changed／changes
    public function get_snapshot_diff($live_snapshot_states, $view_snapshot_states)
    {
        $snapshot_changes = [];

        // 要比對嘅 target＝當刻快照有嘅 key
        $target_names = array_keys($live_snapshot_states);

        // 逐個 target 比對
        foreach ($target_names as $target_name) {
            $live_state = $live_snapshot_states[$target_name] ?? null;
            $view_state = $view_snapshot_states[$target_name] ?? null;

            // 判定：當刻冇此 target → changed 0
            if ($live_state === null) {
                $snapshot_changes[$target_name]["changed"] = 0;
                $snapshot_changes[$target_name]["added"]   = [];
                $snapshot_changes[$target_name]["updated"] = [];
                $snapshot_changes[$target_name]["deleted"] = [];
                continue;
            }

            $live_entries = $live_state["entries"] ?? [];
            $view_entries = ($view_state === null) ? [] : ($view_state["entries"] ?? []);

            $snapshot_changes[$target_name] = sc_stream()->monitor()->get_snapshot_diff($target_name, $live_entries, $view_entries);
        }

        return $snapshot_changes;
    }

    // 開頁基線（寫入 static viewer id；之後用 get_view_id() 取）
    public function init_snapshot()
    {
        $view_id = sc_stream()->snapshot()->create_view_snapshot();

        // 判定：view 檔寫唔入 → 停（頁面照出，只係冇基線）
        if ($view_id === "") {
            return;
        }

        // 逐個 target 初始化（state 線——各 target 自己 is_ready 判斷）
        sc_stream()->snapshot()->state()->init_snapshot();
        sc_stream()->snapshot()->state()->sync_snapshot_views($view_id);

        sc_stream()->snapshot()->prompt()->init_snapshot();
        sc_stream()->snapshot()->prompt()->sync_snapshot_views($view_id);
        
    }
}
