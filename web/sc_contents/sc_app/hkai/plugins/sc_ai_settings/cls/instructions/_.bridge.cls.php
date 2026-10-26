<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions;

// == sc_ai_settings instructions：instructions 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->instructions()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：默認文本 item（兩份皆唯讀——system_prompt 由 sc_config 覆寫、system_env 由 Python 生成；get＝砌頁、fetch＝讀檔——逐 item 一套）——item 表常數化（INSTRUCTIONS_ITEMS）；manifest＝出廠默認（永不寫）、storage＝工作檔 ==

class instructions_Class
{
    use view\get_system_prompt;
    use view\fetch_system_prompt;
    use view\get_system_env;
    use view\fetch_system_env;
    use action\handle_reset_instructions;
    use action\handle_refresh_system_env;

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

    // 判定：instructions_name 是否合法（交 instruction 域——item 表單一來源）
    private function validate_instructions_name($instructions_name)
    {
        return hkai()->process()->instruction()->is_valid_name($instructions_name);
    }

    // 判定：item 可否編輯（交 instruction 域）
    private function instructions_is_editable($instructions_name)
    {
        return hkai()->process()->instruction()->is_editable($instructions_name);
    }

}
