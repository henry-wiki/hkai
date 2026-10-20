<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions;

// == sc_ai_settings instructions：instructions 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->instructions()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：默認文本 item（system_prompt 可編輯、system_env 自動偵測唯讀；get＝砌頁、fetch＝讀檔——逐 item 一套）——manifest＝出廠默認（永不寫）、storage＝工作檔（modal 編輯目標） ==

class instructions_Class
{
    use view\get_system_prompt;
    use view\fetch_system_prompt;
    use view\get_system_env;
    use view\fetch_system_env;
    use action\handle_settings_save_instructions;
    use action\handle_reset_instructions;
    use action\handle_refresh_system_env;
    use action\ensure_system_instructions;

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

    // 判定：instructions_name 是否合法（runtime SSOT instructions 清單的 key——防未知檔名）
    private function validate_instructions_name($instructions_name)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $instructions    = $runtime_configs["instructions"] ?? [];

        return array_key_exists($instructions_name, $instructions);
    }

    // 判定：item 可否編輯（runtime SSOT instructions.<item>.editable——1＝可編輯、0＝唯讀）
    private function instructions_is_editable($instructions_name)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $instructions    = $runtime_configs["instructions"];
        $item_config     = $instructions[$instructions_name];

        return (int)$item_config["editable"] === 1;
    }

    // 取兩條路徑（回 storage_file／manifest_file 絕對路徑；相對名一律來自 runtime SSOT——「名稱不含路徑」原則）
    private function get_instructions_paths($instructions_name)
    {
        $runtime_configs = hkai__core()->get_runtime_configs();
        $storage_dir     = $runtime_configs["storage_dir"];
        $instructions    = $runtime_configs["instructions"];

        // 相對名（例：instructions/system_prompt.md——storage 與配置目錄用同一相對名；item＝{path, editable}）
        $relative_name = $instructions[$instructions_name]["path"];

        // 配置目錄＝hkai_config.json 所在（sc_config/hkai——SSOT 同層）
        $manifest_dir = dirname(hkai__core()->get_hkai_config_file());

        $paths = [];
        $paths["storage_file"]  = $storage_dir . "/" . $relative_name;
        $paths["manifest_file"] = $manifest_dir . "/" . $relative_name;

        return $paths;
    }

    // 保證 storage 工作檔存在（唔存在 → 由 manifest 出廠默認 copy 過去——開頁／start／send 前 idempotent）
    private function ensure_instructions_file($instructions_name)
    {
        $paths = $this->get_instructions_paths($instructions_name);

        // 判定：storage 工作檔已存在 → 直回
        if (is_file($paths["storage_file"])) {
            return $paths["storage_file"];
        }

        // 判定：manifest 出廠默認不存在 → 無法 copy（回空——呼叫端自行處理）
        if (!is_file($paths["manifest_file"])) {
            return "";
        }

        $storage_subdir = dirname($paths["storage_file"]);

        // 判定：storage 子目錄不存在 → 遞迴建立
        if (!is_dir($storage_subdir)) {
            @mkdir($storage_subdir, 0777, true);
        }

        @copy($paths["manifest_file"], $paths["storage_file"]);

        return $paths["storage_file"];
    }
}
