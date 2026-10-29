<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt;

// == sc_ai_chats prompt\prompt_Class（bridge 檔）：prompt 域本體（singleton——bridging sc_ai_chats()->prompt()） ==
// == 動作（cls/prompt/ 各 trait——一檔一動作）：prompt_send（送出端點）／guardalidate_prompt_gates（三關驗證）／view（渲染）／is_prompt_working（狀態判準）／queue_list（等待列端點） ==
// == 私有共用（非動作——住本體）：render_empty_queue_list（空容器標記——view／queue_list 共用） ==
// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名） ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class prompt_Class
{
    use request\prompt_send;
    use guard\validate_prompt_gates;
    use guard\assert_queue_capacity;
    use guard\validate_prompt_prerequisites;
    use view\render_input_stack;
    use view\render_queue_list;
    use request\prompt_fetch;
    use view\render_chat_prompt;
    use view\render_chat_prompt_error;
    use view\get_chat_options_html;
    use view\get_input_dock_html;
    use view\get_prompt_toolbar_html;
    use view\is_prompt_working;
    use request\queue_list;
    use request\append_failed_message_html;
    use request\record_failed_message;
    use request\rollback_created_chat;
    use request\handle_prompt_stop;
    use request\handle_prompt_resume;
    use request\agent_switch;
    use file\get_empty_prompt_data;
    use meta\get_prompt_meta;
    use file\parse_polling_json_file;
    use request\prepare_prompt_request;
    use request\resolve_prompt_from_draft;
    use guard\validate_prompt_processable;
    use request\upload_attachment;


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

    // 隱藏空容器（未選對話／冇排隊——單一組裝來源）
    public function render_empty_queue_list()
    {
        return "<div class='sc_composer__queue_list' hidden></div>";
    }
}
