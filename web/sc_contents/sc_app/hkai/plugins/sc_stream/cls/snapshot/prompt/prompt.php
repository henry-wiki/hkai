<?php

namespace sc\app\hkai\plugins\sc_stream\snapshot\prompt;

// == prompt_Class：prompt 線刷新（singleton——經 sc_stream()->snapshot()->prompt() 取得） ==
// == 上游：sse_prompt::get_snapshot_changes_for_prompt()（每 0.1 秒 步驟①） ==
// == 下游：monitor()->prompt()->init_snapshot() ==

class prompt_Class
{
    // 本線監視 target 清單（prompt 線一個）
    public $snapshot_targets = ["prompt"];

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

    // 初始化快照本體（prompt target——只寫，唔讀）
    public function init_snapshot()
    {
        sc_stream()->monitor()->prompt()->init_snapshot();
    }

    // 刷新快照本體（prompt 線每 0.1 秒——同 init_snapshot 同一份工作）
    public function refresh_snapshot()
    {
        $this->init_snapshot();
    }

    // 讀當刻 prompt 快照（同 state 版同形：{target: state}）；冇檔 → null
    public function get_live_snapshot_states()
    {
        $target_states = [];

        $target_states["prompt"] = sc_stream()->monitor()->get_target_snapshot("prompt");

        return $target_states;
    }

    // 讀本頁已見版本（prompt 線——view/<target>/<view_id>.json → {target: state}）
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

    // 同步本頁已見版本（寫 prompt/view/<view_id>.json）
    public function sync_snapshot_views($view_id)
    {
        // 判定：冇 view id → 唔寫
        if ($view_id === "") {
            return;
        }

        sc_stream()->monitor()->prompt()->sync_snapshot_to_view($view_id);
    }
}
