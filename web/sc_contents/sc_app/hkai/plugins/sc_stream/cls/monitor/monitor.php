<?php

namespace sc\app\hkai\plugins\sc_stream\monitor;

// == monitor_Class：監視對象容器（singleton——清單＋逐個取快照／比對） ==
// == 上游：sc_stream()->monitor()；下游：各監視對象 class（fetch_source_entries／get_snapshot／write_snapshot／get_snapshot_diff_for_*） ==
// == 契約：對象需 fetch_source_entries()／get_live_snapshot()／write_snapshot()／is_ready()／init_snapshot()／get_snapshot_diff()；清單在此一處宣告 ==

class monitor_Class
{
    // 監視對象（第一次用到才建立；target 名 → 物件）
    private static $target_monitors = null;

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

    /**
     * prompt 監視對象
     *
     * @return \sc\app\hkai\plugins\sc_stream\monitor\prompt\prompt_Class
     */
    public function prompt()
    {
        return prompt\prompt_Class::getInstance();
    }

    /**
     * chat 監視對象（對話列表）
     *
     * @return \sc\app\hkai\plugins\sc_stream\monitor\chat\chat_Class
     */
    public function chat()
    {
        return chat\chat_Class::getInstance();
    }

    /**
     * message 監視對象（最近訊息）
     *
     * @return \sc\app\hkai\plugins\sc_stream\monitor\message\message_Class
     */
    public function message()
    {
        return message\message_Class::getInstance();
    }

    /**
     * project 監視對象（專案列表）
     *
     * @return \sc\app\hkai\plugins\sc_stream\monitor\project\project_Class
     */
    public function project()
    {
        return project\project_Class::getInstance();
    }

    /**
     * polling 監視對象（輸送帶 polling 目錄——檔案層）
     *
     * @return \sc\app\hkai\plugins\sc_stream\monitor\polling\polling_Class
     */
    public function polling()
    {
        return polling\polling_Class::getInstance();
    }

    // 逐 target 讀自己嘅快照檔（冇檔 → null）
    public function get_target_snapshot($target_name)
    {
        $target_monitors  = self::get_target_monitors();
        $monitor_instance = $target_monitors[$target_name] ?? null;

        // 判定：冇此對象 → null
        if ($monitor_instance === null) {
            return null;
        }

        $target_snapshot = $monitor_instance->get_live_snapshot();

        // 判定：冇快照 → null
        if ($target_snapshot === null) {
            return null;
        }

        return $target_snapshot["state"] ?? null;
    }

    // 此刻可否初始化（交 target 自己判斷需要咩——例：git 要選了專案）
    public function is_target_ready($target_name)
    {
        $target_monitors  = self::get_target_monitors();
        $monitor_instance = $target_monitors[$target_name] ?? null;

        // 判定：冇此對象 → 當唔需要
        if ($monitor_instance === null) {
            return false;
        }

        return $monitor_instance->is_ready();
    }

    public function get_target_source_entries($target_name)
    {
        $target_monitors  = self::get_target_monitors();
        $monitor_instance = $target_monitors[$target_name] ?? null;

        // 判定：冇此對象 → 空 entries
        if ($monitor_instance === null) {
            return [];
        }

        return $monitor_instance->fetch_source_entries();
    }


    // 交由 target 自己比對（一個入口——target 回 {changed, added, updated, deleted}）
    public function get_snapshot_diff($target_name, $live_entries, $view_entries)
    {
        $target_monitors = self::get_target_monitors();

        $monitor_instance = $target_monitors[$target_name] ?? null;

        // 判定：冇此對象 → 空結果
        if ($monitor_instance === null) {
            return ["changed" => 0, "added" => [], "updated" => [], "deleted" => []];
        }

        return $monitor_instance->get_snapshot_diff($live_entries, $view_entries);
    }

    // 取監視對象實例清單（第一次呼叫才建立）
    private static function get_target_monitors()
    {
        // 判定：未初始化 → 建立清單
        if (self::$target_monitors === null) {
            self::$target_monitors = self::get_monitor_list();
        }

        return self::$target_monitors;
    }

    // 監視對象清單（唯一來源——新增對象喺呢度加一行）
    private static function get_monitor_list()
    {
        $target_monitors = [];

        $target_monitors["prompt"]  = self::getInstance()->prompt();
        $target_monitors["chat"]    = self::getInstance()->chat();
        $target_monitors["message"] = self::getInstance()->message();
        $target_monitors["project"] = self::getInstance()->project();
        $target_monitors["polling"] = self::getInstance()->polling();

        return $target_monitors;
    }
}
