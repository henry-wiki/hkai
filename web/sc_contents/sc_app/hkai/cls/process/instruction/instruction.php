<?php

namespace sc\app\hkai\cls\process\instruction;

// == instruction_Class：指示檔域根（單一入口；經 hkai()->process()->instruction() 取得） ==
// == 職責：按 item 名分派讀寫（system_prompt／system_env 各自成檔）；路徑單一來源；兩份就位檢查 ==
// == 上游：server（啟動同步）、sc_ai_settings instructions（設定頁讀寫）
// == 下游：instruction_sys_prompt（prompt 單元）／instruction_sys_env（env 單元）、sc_config

class instruction_Class
{
    // 兩份指示檔（唯一清單——item 名＝sc_config key 尾段／檔名）
    private const INSTRUCTIONS_NAMES = ["system_prompt", "system_env"];

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

    // system_prompt 單元（唯讀：由 sc_config 每次覆寫）
    public function system_prompt()
    {
        return instruction_sys_prompt_Class::getInstance();
    }

    // system_env 單元（唯讀：由 Python 生成，只補缺檔）
    public function system_env()
    {
        return instruction_sys_env_Class::getInstance();
    }

    // 判定：item 名是否合法（防未知檔名）
    public function is_valid_name($instructions_name)
    {
        return in_array($instructions_name, self::INSTRUCTIONS_NAMES, true);
    }

    // 判定：item 可否編輯（兩份皆唯讀——editable 一律 false）
    public function is_editable($instructions_name)
    {
        return false;
    }

    // 讀現值（分派 item 單元）
    public function read_instructions($instructions_name)
    {
        return $this->get_item($instructions_name)->read_instructions();
    }

    // 同步指示檔（分派 item 單元：prompt 每次覆寫、env 只補缺；回傳成功與否）
    public function sync_instructions($instructions_name)
    {
        // 判定：system_prompt → prompt 單元同步
        if ($instructions_name === "system_prompt") {
            return $this->system_prompt()->sync_sys_prompt();
        }

        return $this->system_env()->sync_sys_env();
    }

    // 同步兩份指示檔（啟動流程用——prompt 每次覆寫、env 由 bridge JSON 重建；各自失敗自行記 log）
    public function sync_system_instructions()
    {
        $this->system_prompt()->sync_sys_prompt();
        $this->system_env()->sync_sys_env();
    }

    // 刪 storage（分派 item 單元）
    public function remove_instructions($instructions_name)
    {
        return $this->get_item($instructions_name)->remove_instructions();
    }

    // 按 item 名取單元（呼叫端已驗名；未知名＝程式錯誤，交 PHP 原生錯誤）
    private function get_item($instructions_name)
    {
        // 判定：system_prompt → prompt 單元
        if ($instructions_name === "system_prompt") {
            return $this->system_prompt();
        }

        return $this->system_env();
    }
}
