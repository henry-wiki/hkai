<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\action;

// == sc_ai_settings instructions ensure_system_instructions：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==
// == 職責：ensure_system_instructions——發問前保證系統級指示檔可得（instructions/system_prompt.md／system_env.md；缺＝由 manifest 默認補；皆缺＝錯誤） ==

trait ensure_system_instructions
{
    // 發問前保證系統級兩檔可得（缺＝由 manifest 默認補一份；連默認都無／補後仍缺＝回 false＝錯誤）
    public function ensure_system_instructions()
    {
        // ① system_prompt（永遠取系統級 root——唔落任何副本）
        $system_prompt_file = $this->ensure_instructions_file("system_prompt");

        // 判定：系統級檔攞唔到（連 manifest 默認都無）→ 回 false（機制錯誤——不可以取唔到）
        if ($system_prompt_file === "") {
            return false;
        }

        // 判定：補完之後檔案仍然唔存在（copy 失敗）→ 回 false（機制錯誤——不可以取唔到）
        if (!is_file($system_prompt_file)) {
            return false;
        }

        // ② system_env（永遠取系統級 root——唔落任何副本）
        $system_env_file = $this->ensure_instructions_file("system_env");

        // 判定：系統級檔攞唔到（連 manifest 默認都無）→ 回 false（機制錯誤——不可以取唔到）
        if ($system_env_file === "") {
            return false;
        }

        // 判定：補完之後檔案仍然唔存在（copy 失敗）→ 回 false（機制錯誤——不可以取唔到）
        if (!is_file($system_env_file)) {
            return false;
        }

        return true;
    }
}
