<?php

namespace sc\app\hkai\plugins\sc_ai_chats\permission;

// == sc_ai_chats permission\permission_Class：permission 域本體（bridge 檔——singleton；bridging sc_ai_chats()->permission()） ==
// == 職責：goose ACP 工具權限流——請求側（收前端決定寫決定檔）＋顯示側（讀請求砌提示卡） ==
// == 檔案契約（與 Python ACP client 對稱——系統唯一契約）：msg_permission.json（Python 寫／本側讀）＋ msg_permission_decision.json（本側寫／Python 讀） ==

class permission_Class
{
    use request\handle_permission_decide;
    use view\build_permission_prompt;

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
