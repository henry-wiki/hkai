<?php

namespace sc\app\hkai\plugins\sc_stream\snapshot\state;

// == state_Class：state 線刷新（singleton——經 sc_stream()->snapshot()->state() 取得） ==
// == 上游：sse_state::get_snapshot_changes_for_state()（每 tick 步驟①） ==
// == 下游：monitor()->{chat,project,polling,git,conn,message}()->init_snapshot() ==

class state_Class
{
    // 本線監視 target 清單（state 線六個）
    public $snapshot_targets = ["chat", "project", "message", "polling", "git", "conn"];

    private static $instance = null;

    // 單例入口
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

    // 初始化快照本體（頁面載入——逐個 target 只寫，唔讀）
    public function init_snapshot()
    {
        // 逐個 target 初始化（各 target 自己 is_ready 判斷）
        sc_stream()->monitor()->chat()->init_snapshot();
        sc_stream()->monitor()->project()->init_snapshot();
        sc_stream()->monitor()->polling()->init_snapshot();
        sc_stream()->monitor()->git()->init_snapshot();
        sc_stream()->monitor()->conn()->init_snapshot();

        // message：必須有 chat_sid（is_ready() 判斷；冇 → 跳過）
        sc_stream()->monitor()->message()->init_snapshot();
    }

    // 刷新快照本體（state 線每 tick——同 init_snapshot 同一份工作）
    public function refresh_snapshot()
    {
        $this->init_snapshot();
    }

    // 讀當刻六個 target 快照（唔適用嘅 target 交 null——唔參與比對）
    public function get_live_snapshot_states()
    {
        $target_states = [];

        $target_states["chat"]    = $this->get_ready_target_snapshot("chat");
        $target_states["project"] = $this->get_ready_target_snapshot("project");
        $target_states["message"] = $this->get_ready_target_snapshot("message");
        $target_states["polling"] = $this->get_ready_target_snapshot("polling");
        $target_states["git"]     = $this->get_ready_target_snapshot("git");
        $target_states["conn"]    = $this->get_ready_target_snapshot("conn");

        return $target_states;
    }

    // 取單一 target 快照（此刻唔適用 → null；例：未選專案時 git）
    private function get_ready_target_snapshot($target_name)
    {
        $is_ready = sc_stream()->monitor()->is_target_ready($target_name);

        // 判定：此 target 此刻唔適用 → null
        if ($is_ready === false) {
            return null;
        }

        return sc_stream()->monitor()->get_target_snapshot($target_name);
    }

    // 讀本頁已見版本（六個 state target——view/<target>/<view_id>.json → {target: state}）
    public function get_view_snapshot_states($view_id)
    {
        // 判定：冇 view id → 空
        if ($view_id === "") {
            return [];
        }

        $view_states      = [];
        $target_name_list = $this->snapshot_targets;

        // 逐個 target 讀本頁自己嗰個基準檔
        foreach ($target_name_list as $target_name) {
            $view_file = sc_stream()->snapshot()->get_snapshot_view_file($target_name, $view_id);

            $view_data = sc_read_json($view_file);

            // 判定：冇檔／壞檔 → 略過
            if ($view_data === null) {
                continue;
            }

            $view_state = $view_data["state"] ?? null;

            // 判定：內容唔係陣列 → 略過
            if (is_array($view_state) === false) {
                continue;
            }

            $view_states[$target_name] = $view_state;
        }

        return $view_states;
    }

    // 同步本頁已見版本（逐個 state target 寫 <target>/view/<view_id>.json）
    public function sync_snapshot_views($view_id)
    {
        // 判定：冇 view id → 唔寫
        if ($view_id === "") {
            return;
        }

        sc_stream()->monitor()->chat()->sync_snapshot_to_view($view_id);
        sc_stream()->monitor()->project()->sync_snapshot_to_view($view_id);
        sc_stream()->monitor()->polling()->sync_snapshot_to_view($view_id);
        sc_stream()->monitor()->git()->sync_snapshot_to_view($view_id);
        sc_stream()->monitor()->conn()->sync_snapshot_to_view($view_id);
        sc_stream()->monitor()->message()->sync_snapshot_to_view($view_id);
    }
}
