<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor;

// == monitor_Class：監視對象容器（singleton——清單＋逐個取快照／比對） ==
// == 上游：sc_ai_health()->monitor()；下游：各監視對象 class（fetch_entries／compare_entries） ==
// == 契約：對象需 fetch_entries()＋compare_entries()；清單在此一處宣告（同檔名無關） ==

class monitor_Class
{
    // 監視對象（第一次用到才建立；target 名 → 物件）
    private static $health_monitors = null;

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
     * @return \sc\app\hkai\plugins\sc_ai_health\monitor\prompt\prompt_Class
     */
    public function prompt()
    {
        return prompt\prompt_Class::getInstance();
    }

    /**
     * chat 監視對象（對話列表）
     *
     * @return \sc\app\hkai\plugins\sc_ai_health\monitor\chat\chat_Class
     */
    public function chat()
    {
        return chat\chat_Class::getInstance();
    }

    /**
     * message 監視對象（最近訊息）
     *
     * @return \sc\app\hkai\plugins\sc_ai_health\monitor\message\message_Class
     */
    public function message()
    {
        return message\message_Class::getInstance();
    }

    /**
     * project 監視對象（專案列表）
     *
     * @return \sc\app\hkai\plugins\sc_ai_health\monitor\project\project_Class
     */
    public function project()
    {
        return project\project_Class::getInstance();
    }

    /**
     * git 監視對象（當前專案 git 狀態）
     *
     * @return \scpp\hkai\plugins\sc_ai_health\monitor\git\git_Class
     */
    public function git()
    {
        return git\git_Class::getInstance();
    }

    /**
     * conn 監視對象（後端 bridge 連線狀態）
     *
     * @return \scpp\hkai\plugins\sc_ai_health\monitor\conn\conn_Class
     */
    public function conn()
    {
        return conn\conn_Class::getInstance();
    }

    // 取單一目標 entries（該對象自己嘅 fetch_entries；conn 要頁面 context）
    public function fetch_target_entries($target_name, $monitor_context = [])
    {
        $health_monitors  = self::get_health_monitors();
        $monitor_instance = $health_monitors[$target_name] ?? null;

        // 判定：冇此對象 → 空 entries
        if ($monitor_instance === null) {
            return [];
        }

        // 判定：conn／git 要頁面 context（project_slug）；其餘對象零參數
        if (in_array($target_name, ["conn", "git"], true)) {
            return $monitor_instance->fetch_entries($monitor_context);
        }

        return $monitor_instance->fetch_entries();
    }

    // 單一對象比對（該對象自己嘅 compare_entries）
    public function compare_target_entries($target_name, $previous_entries, $current_entries)
    {
        $health_monitors  = self::get_health_monitors();
        $monitor_instance = $health_monitors[$target_name] ?? null;

        // 判定：冇此對象 → null
        if ($monitor_instance === null) {
            return null;
        }

        return $monitor_instance->compare_entries($previous_entries, $current_entries);
    }

    // 取監視對象清單（第一次呼叫才建立）
    private static function get_health_monitors()
    {
        // 判定：未初始化 → 建立清單
        if (self::$health_monitors === null) {
            self::$health_monitors = self::get_health_monitor_list();
        }

        return self::$health_monitors;
    }

    // 監視對象清單（唯一來源——新增對象喺呢度加一行）
    private static function get_health_monitor_list()
    {
        $health_monitors = [];

        $health_monitors["prompt"]  = self::getInstance()->prompt();
        $health_monitors["chat"]    = self::getInstance()->chat();
        // $health_monitors["message"] = self::getInstance()->message();   // 訊息層唔入 stream.json（改放 data/stream/chats/{chat_sid}.json）
        $health_monitors["project"] = self::getInstance()->project();
        $health_monitors["git"]     = self::getInstance()->git();
        $health_monitors["conn"]    = self::getInstance()->conn();

        return $health_monitors;
    }
}
