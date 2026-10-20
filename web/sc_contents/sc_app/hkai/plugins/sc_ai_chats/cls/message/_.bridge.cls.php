<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message;

// == sc_ai_chats message\message_Class（bridge 檔）：message 域本體（singleton——bridging sc_ai_chats()->message()） ==
// == 動作（cls/message/ 各 trait——一檔一動作；validate 三支為 static） ==
// == 2026-09-27 八個 message class（action／archive／handler／init／repo_select／repo_update／validate／view）合成單一本體 ==
// == 2026-10-05 view 群拆出子域（view_Class——sc_ai_chats()->message()->view()） ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class message_Class
{
    use action\delete_message;
    use action\repair_stale_message_status;
    use archive\archive_msg_files;
    use archive\finalize_poll_job;
    use archive\backfill_message_timing;
    use archive\backfill_response_timing;
    use archive\read_archived_message_status;
    use request\handle_message_delete;
    use validate\validate_message_prerequisites;
    use validate\validate_message_processable;
    use request\message_list_fetch;

    private static $instance = null;
    private $message_page_size = 0;

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
     * repo_select 子域（獨立物件——cls/message/repo_select/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\message\repo_select\repo_select_Class
     */
    public function repo_select()
    {
        return repo_select\repo_select_Class::getInstance();
    }

    /**
     * repo_update 子域（獨立物件——cls/message/repo_update/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\message\repo_update\repo_update_Class
     */
    public function repo_update()
    {
        return repo_update\repo_update_Class::getInstance();
    }

    /**
     * card 子域（獨立物件——cls/message/card/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\message\card\card_Class
     */
    public function card()
    {
        return card\card_Class::getInstance();
    }

    /**
     * normalize 子域（獨立物件——cls/message/normalize/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\message\normalize\normalize_Class
     */
    public function normalize()
    {
        return normalize\normalize_Class::getInstance();
    }

    /**
     * view 子域（獨立物件——cls/message/view/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\message\view\view_Class
     */
    public function view()
    {
        return view\view_Class::getInstance();
    }


    // 每頁訊息數（後端取訊息 LIMIT 用）
    public function get_message_page_size()
    {
        return $this->message_page_size;
    }

    // 注入訊息分頁設定（plugin __init__.php 呼叫一次——集中於入口；class 只收值）
    public function initial_message_page($message_page_setup)
    {
        $this->message_page_size = intval($message_page_setup["page_size"]);
    }


}
