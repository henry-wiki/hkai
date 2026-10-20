<?php

namespace sc\app\hkai\plugins\sc_ai_chats;

// == sc_ai_chats_Class：插件頂層本體（singleton——bridging sc_ai_chats()） ==
// == 職責：域入口（accessor）——例：sc_ai_chats()->queue()->get_queue_file_path($chat_id) ==
// == 2026-09-27 建（域收攏後補頂層單一入口——各域經 accessor：sc_ai_chats()-><域>()） ==

class sc_ai_chats_Class
{

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
    {
    }

    /**
     * 對話域（chat_Class——25 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\chat\chat_Class
     */
    public function chat()
    {
        return chat\chat_Class::getInstance();
    }

    /**
     * 訊息域（message_Class——35 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\message\message_Class
     */
    public function message()
    {
        return message\message_Class::getInstance();
    }

    /**
     * 等待列域（queue_Class——6 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\queue\queue_Class
     */
    public function queue()
    {
        return queue\queue_Class::getInstance();
    }

    /**
     * 輸入域（prompt_Class——15 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\prompt\prompt_Class
     */
    public function prompt()
    {
        return prompt\prompt_Class::getInstance();
    }

    /**
     * 輪詢域（poll_Class——27 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\poll\poll_Class
     */
    public function poll()
    {
        return poll\poll_Class::getInstance();
    }

    /**
     * 程序域（process_Class——2 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\process\process_Class
     */
    public function process()
    {
        return process\process_Class::getInstance();
    }

    /**
     * 狀態機（fsm_Class——4 查詢）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\fsm\fsm_Class
     */
    public function fsm()
    {
        return fsm\fsm_Class::getInstance();
    }

    /**
     * 儲存（storage\storage_Class）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\storage\storage_Class
     */
    public function storage()
    {
        return storage\storage_Class::getInstance();
    }

    /**
     * 健康報告域（health_Class——1 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\health\health_Class
     */
    public function health()
    {
        return health\health_Class::getInstance();
    }

    /**
     * 對話選項域（chat_option_Class——4 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\chat_option\chat_option_Class
     */
    public function chat_option()
    {
        return chat_option\chat_option_Class::getInstance();
    }

    /**
     * 權限域（permission_Class——2 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\permission\permission_Class
     */
    public function permission()
    {
        return permission\permission_Class::getInstance();
    }

}
