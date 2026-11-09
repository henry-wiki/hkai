<?php

namespace sc\app\hkai\plugins\sc_stream\snapshot\state;

// == state_Class：state 線刷新（singleton——經 sc_stream()->snapshot()->state() 取得） ==
// == 上游：sse_state::get_snapshot_changes_for_state()（每 tick 步驟①） ==
// == 下游：monitor()->{chat,project,polling,git,message}()->init_snapshot() ==

class state_Class
{
    // 本線監視 target 清單（state 線四個——git 交 workspace_stream）
    public $snapshot_targets = [];

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
        // state 線唔再初始化任何 target：chat／project／message／polling 一律由 bridge workspace_stream 推（單一來源）

    }

    // 刷新快照本體（state 線每 tick——同 init_snapshot 同一份工作）
    public function refresh_snapshot()
    {
        $this->init_snapshot();
    }

    // 讀當刻五個 target 快照（唔適用嘅 target 交 null——唔參與比對）
    public function get_live_snapshot_states()
    {
        $target_states = [];


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

    // 清本頁已見版本（開線／重連用——清走基準，下一 tick 全部當新增，全量補一次）
    // 上游：sse_state::run_state_stream()（每次開線）
    // 下游：snapshot()->get_snapshot_view_file()（逐 target 刪檔）
    public function reset_view_snapshot_states($view_id)
    {
        // 判定：冇 view id → 唔做
        if ($view_id === "") {
            return;
        }

        // 逐個 target 刪本頁基準檔
        foreach ($this->snapshot_targets as $target_name) {
            $view_file = sc_stream()->snapshot()->get_snapshot_view_file($target_name, $view_id);

            // 判定：檔案存在 → 刪（唔存在＝零動作）
            if (is_file($view_file) === true) {
                @unlink($view_file);
            }
        }
    }

    // 讀本頁已見版本（五個 state target——view/<target>/<view_id>.json → {target: state}）
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

    }
}
