<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message\view：bridge 檔——只放 class（.cls.php＝class 專用；.func.php＝function 專用） ==
// == 載入：autoloader（一般映射先行、本目錄 bridge 檔後備——_.bridge.cls.php 系統指定名） ==
// == 唯一入口：sc_ai_chats()->message()->view() ==

class view_Class
{
    use get_agent_summarize_thinking;
    use get_agent_summarize_result;
    use get_agent_summarize_toolbar;
    use get_agent_response_thinking;
    use get_agent_response_result;
    use get_agent_response_result_toolbar;
    use get_agent_response_thinking_toolbar;
    use get_response_elapsed_sec;
    use format_elapsed_sec;
    use build_response_section;
    use get_agent_response;
    use get_chat_message_slot;
    use render_chat_message_list;
    use render_chat_message_item;
    use render_message_body;
    use get_user_prompt;

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

}
