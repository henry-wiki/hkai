<?php
namespace sc\framework\foundation\debug;

// sc_error：基本錯誤食者（攔截 PHP error 並顯示）
// register() 掛鉤：set_error_handler（捕 E_USER_ERROR/E_WARNING 等）+ register_shutdown_function（捕 E_ERROR/E_PARSE）
// 顯示：CLI echo／瀏覽器 <pre>；訊息附檔案位置
// 其他 debug 工具可 unregister() 讓路，或經前一 handler 鏈式傳遞

class sc_error_Class
{
    private static $instance = null;

    private $previous_handler = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    // 掛鉤：把自己設為 PHP error handler（保存前一 handler）+ shutdown 捕 fatal
    public function register()
    {
        $this->previous_handler = set_error_handler([$this, "handle_error"]);
        register_shutdown_function([$this, "handle_shutdown"]);
    }

    // 移除自己，恢復前一 handler（給 debug 工具讓路）
    public function unregister()
    {
        restore_error_handler();
    }

    // PHP error handler 入口（PHP 自動傳 4 參數；含 E_USER_ERROR/E_USER_WARNING 等可回復錯誤）
    public function handle_error($errno, $errstr, $errfile, $errline)
    {
        // 被 @ 抑制的錯誤（error_reporting() 唔含呢個 errno）→ 尊重抑制，不處理
        // 註：PHP 8.0+ @ 之下 error_reporting() 唔再係 0（固定致命錯誤 mask 4437）——必須按 bit 檢查
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $full_message = $errstr . " (at " . $errfile . " : " . $errline . ")";

        // 顯示（CLI echo／瀏覽器 <pre>）
        if (PHP_SAPI === "cli") {
            echo "[ERROR " . $errno . "] " . $full_message . PHP_EOL;
        } else {
            echo "<pre style='color:red;'>[ERROR " . $errno . "] "
                . htmlspecialchars($full_message) . "</pre>";
        }

        // 嚴重錯誤 → 中止
        if (in_array($errno, [E_USER_ERROR, E_ERROR, E_PARSE], true)) {
            die();
        }

        // 前一 handler 存在 → 鏈式傳遞（讓 debug 工具也處理）
        if ($this->previous_handler !== null) {
            return call_user_func($this->previous_handler, $errno, $errstr, $errfile, $errline);
        }

        return true;
    }

    // shutdown 入口（script 結束時呼叫；捕 E_ERROR/E_PARSE 等 fatal——error handler 攔不到的）
    public function handle_shutdown()
    {
        $error = error_get_last();
        if ($error !== null && in_array($error["type"], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
            $full_message = $error["message"] . " (at " . $error["file"] . " : " . $error["line"] . ")";

            if (PHP_SAPI === "cli") {
                echo "[FATAL] " . $full_message . PHP_EOL;
            } else {
                echo "<pre style='color:red;'>[FATAL] " . htmlspecialchars($full_message) . "</pre>";
            }
        }
    }
}
