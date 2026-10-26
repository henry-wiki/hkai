<?php

namespace sc\app\hkai\cls\process\instruction;

// == instruction_sys_prompt_Class：只處理 system_prompt.md（單一 item；經 hkai()->process()->instruction()->system_prompt() 取得） ==
// == 職責：system_prompt 嘅「出廠默認（sc_config）→ 執行期檔（data/instructions）」路徑、讀、寫、同步、刪 ==
// == 上游：instruction_Class（分派）／server（啟動同步 sync_sys_prompt）
// == 下游：sc_config（hkai.instructions.system_prompt／hkai.instructions_dir）、檔案系統

class instruction_sys_prompt_Class
{
    // 本 item 檔名（＝sc_config key 尾段／執行期檔名）
    private const INSTRUCTIONS_NAME = "system_prompt";

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

    // 讀現值（執行期檔優先；無 → 出廠默認；皆無 → 空內容）
    public function read_instructions()
    {
        $runtime_sys_prompt_file = $this->get_runtime_sys_prompt_file();

        $read_result = [];
        $read_result["content"]    = "";
        $read_result["updated_at"] = "";

        // 判定：執行期檔存在 → 讀現值＋最後更新時間
        if (is_file($runtime_sys_prompt_file)) {
            $read_result["content"]    = (string)@file_get_contents($runtime_sys_prompt_file);
            $read_result["updated_at"] = date("Y-m-d H:i:s", (int)@filemtime($runtime_sys_prompt_file));

            return $read_result;
        }

        $sys_prompt_file = sc_get_config("hkai.instructions.system_prompt");

        // 判定：出廠默認存在 → 讀默認內容（唔 copy）
        if (is_file($sys_prompt_file)) {
            $read_result["content"] = (string)@file_get_contents($sys_prompt_file);
        }

        return $read_result;
    }

    // 同步 system_prompt（出廠默認 → 執行期檔；每次覆寫——回傳成功與否）
    public function sync_sys_prompt()
    {
        $sys_prompt_file         = sc_get_config("hkai.instructions.system_prompt");
        $runtime_sys_prompt_file = $this->get_runtime_sys_prompt_file();

        // 判定：出廠默認不存在 → 無法同步（false）
        if (!is_file($sys_prompt_file)) {
            return false;
        }

        $runtime_subdir = dirname($runtime_sys_prompt_file);

        // 判定：執行期目錄不存在 → 建立（記 log）
        if (!is_dir($runtime_subdir)) {
            hkai()->runtime()->logger()->info("sync_sys_prompt: create dir " . $runtime_subdir);

            $is_mkdir_ok = @mkdir($runtime_subdir, 0777, true);

            // 判定：建立失敗 → 記 log（回 false）
            if ($is_mkdir_ok !== true) {
                hkai()->runtime()->logger()->error("sync_sys_prompt: mkdir failed — " . $runtime_subdir);

                return false;
            }
        }

        $is_copy_ok = @copy($sys_prompt_file, $runtime_sys_prompt_file);

        // 判定：copy 失敗 → 記 log（回 false）
        if ($is_copy_ok !== true) {
            hkai()->runtime()->logger()->error("sync_sys_prompt: copy failed — " . $sys_prompt_file . " → " . $runtime_sys_prompt_file);

            return false;
        }

        return is_file($runtime_sys_prompt_file);
    }

    // 刪執行期檔（Reset——下次同步由出廠默認重建）
    public function remove_instructions()
    {
        $runtime_sys_prompt_file = $this->get_runtime_sys_prompt_file();

        // 判定：執行期檔存在 → 刪走
        if (is_file($runtime_sys_prompt_file)) {
            return @unlink($runtime_sys_prompt_file);
        }

        return true;
    }

    // 取執行期檔路徑（data/instructions/system_prompt.md——agent 實際讀嗰份）
    private function get_runtime_sys_prompt_file()
    {
        $instructions_dir = sc_get_config("hkai.instructions_dir");

        return $instructions_dir . "/" . self::INSTRUCTIONS_NAME . ".md";
    }
}
