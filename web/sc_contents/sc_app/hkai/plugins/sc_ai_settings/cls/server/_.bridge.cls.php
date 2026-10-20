<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server;

// == sc_ai_settings server：server 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->server()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat server_Class 合成） ==
// == 職責：server_settings 讀寫（runtime.json 動態層優先——test／save 寫入；靜態 SSOT 永不寫入） ==

class server_Class
{
    use view\handle_fetch_server_settings;
    use view\get_modal_content;
    use view\handle_fetch_server_log;
    use action\get_host_port_test_result;
    use action\handle_settings_test_server;
    use action\handle_settings_start_server;
    use action\handle_settings_restart_server;
    use action\handle_settings_stop_server;
    use action\handle_settings_auto_assign_port;

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

    // 驗證 host／port（回 errors 陣列——test 與 save 共用；空陣列＝通過）
    private function validate_host_port($server_host, $server_port)
    {
        $errors          = [];
        $server_port_int = (int)$server_port;

        // 判定：host 為空 → 錯誤
        if ($server_host === "") {
            $errors["host"] = hkai__i18n()->get("settings.server.error_host_empty");
        }

        // 判定：port 為空 → 錯誤
        if ($server_port === "") {
            $errors["port"] = hkai__i18n()->get("settings.server.error_port_empty");
        }

        // 判定：port 非空但非純數字 → 錯誤
        if ($server_port !== "") {
            if (!ctype_digit($server_port)) {
                $errors["port"] = hkai__i18n()->get("settings.server.error_port_not_number");
            }
        }

        // 判定：port 為純數字 → 檢查範圍
        if (ctype_digit($server_port)) {
            // 判定：小於 1 → 錯誤
            if ($server_port_int < 1) {
                $errors["port"] = hkai__i18n()->get("settings.server.error_port_range");
            }
            // 判定：大於 65535 → 錯誤
            if ($server_port_int > 65535) {
                $errors["port"] = hkai__i18n()->get("settings.server.error_port_range");
            }
        }

        return $errors;
    }

    // 日誌行組 → HTML（倒序——最新喺頂；逐行上色）
    private function render_server_log_lines($log_lines)
    {
        $line_htmls = [];

        // 倒序（最新行排頭——對應 flex 柱 prepend 顯示）
        $reversed_log_lines = array_reverse($log_lines);

        // 逐行渲染
        foreach ($reversed_log_lines as $log_line) {
            $line_htmls[] = $this->render_server_log_line($log_line);
        }

        return implode("\n", $line_htmls);
    }

    // 日誌單行 → HTML（時間 dim／層級上色；格式唔符＝原文一行；先 escape 再包 span）
    private function render_server_log_line($log_line)
    {
        $log_line_encoded = sc_html_encode($log_line);

        // 解析標準格式：時間 [層級] 後段
        $matched = preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (\[[A-Z]+\]) (.*)$/", $log_line_encoded, $log_line_parts);

        // 判定：唔係標準行（traceback 等）→ 原文一行
        if ($matched !== 1) {
            return "<div class='settings_server_log_line'>" . $log_line_encoded . "</div>";
        }

        $server_log_time  = $log_line_parts[1];
        $server_log_level = $log_line_parts[2];
        $server_log_body  = $log_line_parts[3];

        // 層級色 class（INFO 藍／WARNING 橙／ERROR 紅；未知＝底灰）
        $server_log_level_class = "";

        // 判定：INFO 系
        if (in_array($server_log_level, ["[INFO]", "[DEBUG]"])) {
            $server_log_level_class = " settings_server_log_level--info";
        }
        // 判定：WARNING 系
        else if (in_array($server_log_level, ["[WARNING]", "[WARN]"])) {
            $server_log_level_class = " settings_server_log_level--warn";
        }
        // 判定：ERROR 系
        else if (in_array($server_log_level, ["[ERROR]", "[CRITICAL]"])) {
            $server_log_level_class = " settings_server_log_level--error";
        }

        $line_html_parts = [];
        $line_html_parts[] = "<div class='settings_server_log_line'>";
        $line_html_parts[] = "<span class='settings_server_log_time'>" . $server_log_time . "</span> ";
        $line_html_parts[] = "<span class='settings_server_log_level" . $server_log_level_class . "'>" . $server_log_level . "</span> ";
        $line_html_parts[] = $server_log_body;
        $line_html_parts[] = "</div>";

        return implode("", $line_html_parts);
    }
}
