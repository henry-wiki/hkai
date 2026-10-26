<?php

namespace sc\app\hkai\cls\process\instruction;

// == instruction_sys_env_Class：只處理 system_env.md（單一 item；經 hkai()->process()->instruction()->system_env() 取得） ==
// == 職責：由 bridge /diagnostics/system_env/get 取 JSON → 按 CONTENT_TEMPLATE 砌行 → 寫 data/instructions/system_env.md ==
// == 上游：instruction_Class（分派）／server（啟動同步 sync_sys_env）／settings 刷新
// == 下游：bridge（get JSON）、sc_config（instructions_dir）、檔案系統

class instruction_sys_env_Class
{
    // 本 item 檔名（＝sc_config key 尾段／執行期檔名）
    private const INSTRUCTIONS_NAME = "system_env";

    // 資料佔位符（sc_config template 內——生成時整段替換；模板＝檔頭＋說明，資料＝執行期砌）
    private const DATA_PLACEHOLDER = "<!--[[SYSTEM_ENV_DATA]]-->";

    // 內容模板（每項＝一段事實；json_key＝bridge JSON 取值位置；renderer＝對應組法——加／減段落只需改此表）
    private const CONTENT_TEMPLATE = [
        ["json_key" => "os", "label" => "OS / Hostname", "renderer" => sys_env_renderer\os_Class::class],
        ["json_key" => "hardware", "label" => "CPU / Memory", "renderer" => sys_env_renderer\hardware_Class::class],
        ["json_key" => "disk", "label" => "Disk", "renderer" => sys_env_renderer\disk_Class::class],
        ["json_key" => "network", "label" => "Network", "renderer" => sys_env_renderer\network_Class::class],
        ["json_key" => "container", "label" => "Container", "renderer" => sys_env_renderer\container_Class::class],
        ["json_key" => "shell", "label" => "Shell", "renderer" => sys_env_renderer\shell_Class::class],
        ["json_key" => "timezone", "label" => "Timezone", "renderer" => sys_env_renderer\timezone_Class::class],
        ["json_key" => "python_version", "label" => "Python", "renderer" => sys_env_renderer\python_Class::class],
        ["json_key" => "runtimes", "label" => "Runtimes", "renderer" => sys_env_renderer\runtimes_Class::class],
        ["json_key" => "tools", "label" => "Tools", "renderer" => sys_env_renderer\tools_Class::class],
        ["json_key" => "detected_at", "label" => "Detected at", "renderer" => sys_env_renderer\detected_at_Class::class],
    ];

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
        $runtime_sys_env_file = $this->get_runtime_sys_env_file();

        $read_result = [];
        $read_result["content"]    = "";
        $read_result["updated_at"] = "";

        // 判定：執行期檔存在 → 讀現值＋最後更新時間
        if (is_file($runtime_sys_env_file)) {
            $read_result["content"]    = (string)@file_get_contents($runtime_sys_env_file);
            $read_result["updated_at"] = date("Y-m-d H:i:s", (int)@filemtime($runtime_sys_env_file));

            return $read_result;
        }

        $sys_env_file = sc_get_config("hkai.instructions.system_env");

        // 判定：出廠默認存在 → 讀默認內容（唔 copy）
        if (is_file($sys_env_file)) {
            $read_result["content"] = (string)@file_get_contents($sys_env_file);
        }

        return $read_result;
    }

    // 同步 system_env：bridge JSON → 按模板砌行 → 寫執行期檔（每次覆寫——回傳成功與否）
    public function sync_sys_env()
    {
        $env_data = hkai()->process()->bridge()->request("/diagnostics/system_env/get", [], 30);

        // 判定：取唔到環境資料（bridge 未起／偵測失敗）→ 記 log（回 false）
        if (($env_data["ok"] ?? false) !== true) {
            $env_error = $env_data["error"] ?? "unknown";
            hkai()->runtime()->logger()->error("sync_sys_env: fetch failed — " . $env_error);

            return false;
        }

        $sys_env_lines     = $this->collect_sys_env_lines($env_data);
        $sys_env_data_text = implode("\r\n", $sys_env_lines);

        // 開 sc_config template → 資料段替換佔位符（模板＝檔頭＋說明；資料＝執行期砌）
        $template_file = sc_get_config("hkai.instructions.system_env");

        // 判定：模板檔唔存在（機制錯誤）→ 記 log（回 false）
        if (!is_file($template_file)) {
            hkai()->runtime()->logger()->error("sync_sys_env: template not found — " . $template_file);

            return false;
        }

        $template_content = (string)@file_get_contents($template_file);
        $content          = str_replace(self::DATA_PLACEHOLDER, $sys_env_data_text, $template_content);

        $runtime_sys_env_file = $this->get_runtime_sys_env_file();
        $runtime_subdir       = dirname($runtime_sys_env_file);

        // 判定：執行期目錄不存在 → 建立（記 log）
        if (!is_dir($runtime_subdir)) {
            hkai()->runtime()->logger()->info("sync_sys_env: create dir " . $runtime_subdir);

            $is_mkdir_ok = @mkdir($runtime_subdir, 0777, true);

            // 判定：建立失敗 → 記 log（回 false）
            if ($is_mkdir_ok !== true) {
                hkai()->runtime()->logger()->error("sync_sys_env: mkdir failed — " . $runtime_subdir);

                return false;
            }
        }

        $is_write_ok = @file_put_contents($runtime_sys_env_file, $content, LOCK_EX);

        // 判定：寫入失敗 → 記 log（回 false）
        if ($is_write_ok === false) {
            hkai()->runtime()->logger()->error("sync_sys_env: write failed — " . $runtime_sys_env_file);

            return false;
        }

        return is_file($runtime_sys_env_file);
    }

    // 刪執行期檔（下次同步由 bridge JSON 重建）
    public function remove_instructions()
    {
        $runtime_sys_env_file = $this->get_runtime_sys_env_file();

        // 判定：執行期檔存在 → 刪走
        if (is_file($runtime_sys_env_file)) {
            return @unlink($runtime_sys_env_file);
        }

        return true;
    }

    // 系統環境內容行（按 CONTENT_TEMPLATE 逐段 renderer 砌）
    private function collect_sys_env_lines($env_data)
    {
        $content_lines = [];

        // 逐模板段（順序＝輸出順序；每段回行 array）
        foreach (self::CONTENT_TEMPLATE as $template_item) {
            $renderer_class = $template_item["renderer"];
            $rendered_lines = $renderer_class::render($env_data);

            $content_lines = array_merge($content_lines, $rendered_lines);
        }

        return $content_lines;
    }

    // 取執行期檔路徑（data/instructions/system_env.md——agent 實際讀嗰份）
    private function get_runtime_sys_env_file()
    {
        $instructions_dir = sc_get_config("hkai.instructions_dir");

        return $instructions_dir . "/" . self::INSTRUCTIONS_NAME . ".md";
    }
}
