<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\system_env;

// == sc_ai_diagnostics system_env：system_env 域本體（bridge 檔——singleton；呼叫形 sc_ai_diagnostics()->system_env()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：系統級環境偵測資料的索要／寫檔／渲染（Python 偵測 → PHP 落 manifest 環境檔①md） ==

class system_env_Class
{
    use action\run_system_env_fetch;
    use action\write_system_env_file;
    use action\render_system_env_markdown;

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

    // 環境檔路徑（配置目錄＝hkai_config.json 所在〔sc_config/hkai〕——SSOT 同層；名由 hkai_config filenames 帶）
    private function get_system_env_paths()
    {
        $manifest_dir = dirname(hkai__core()->get_hkai_config_file());
        $filenames    = hkai__core()->get_hkai_configs("filenames");

        $system_env_md_name = $filenames["system_env_markdown"];

        $env_paths = [];
        $env_paths["manifest_dir"]  = $manifest_dir;
        $env_paths["markdown_file"] = $manifest_dir . "/" . $system_env_md_name;

        return $env_paths;
    }

    // 單檔是否齊備（Markdown 存在先當已生成——缺即重寫）
    private function check_system_env_file_exist()
    {
        $env_paths = $this->get_system_env_paths();

        // 判定：Markdown 缺 → 未齊備
        if (!is_file($env_paths["markdown_file"])) {
            return false;
        }

        return true;
    }

    // 原子寫檔（先 .tmp 再 rename——讀者永遠見到完整檔；回傳成敗）
    private function write_file_atomic($target_file, $file_content)
    {
        $temp_file = $target_file . ".tmp";

        // 判定：tmp 寫入失敗 → false（不動目標檔）
        if (@file_put_contents($temp_file, $file_content, LOCK_EX) === false) {
            return false;
        }

        // 判定：rename 失敗 → 清理 tmp、false
        if (!@rename($temp_file, $target_file)) {
            @unlink($temp_file);
            return false;
        }

        return true;
    }
}
