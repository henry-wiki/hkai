<?PHP
namespace sc\framework\foundation\session;

// sc_session：session 管理器（對齊 Laravel SessionManager + Store 概念）
// driver 分派：default（PHP 原生，不掛 handler）／file（可指定儲存位置）／database（預留）
// 用戶 API：put/get/put_group/get_group/forget（對齊 Laravel Store）

class sc_session_Class
{
    private static $instance = null;

    public function __construct()
    {
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function initial()
    {
    }

    // 啟動 session（安全啟動：未啟動才 start——防重複啟動警告）
    public function start()
    {
        // 判定：session 已啟動 → 完成
        if (session_status() != PHP_SESSION_NONE) {
            return;
        }

        // 暫時接管 error handler：吞掉 session_start 嘅警告（損壞 session 檔唔應該顯示畀用戶）
        set_error_handler(function () {
            return true;
        });

        $session_started = session_start();

        // 判定：啟動成功 → 還原 handler 後完成
        if ($session_started) {
            restore_error_handler();
            return;
        }

        // 啟動失敗（session 檔損壞）：換新 session id 再試一次
        session_id(bin2hex(random_bytes(16)));
        session_start();
        restore_error_handler();
    }

    // 分派 session driver（對齊 Laravel config/session.php 的 driver 選擇）
    // default = 不呼叫 session_set_save_handler → PHP 原生 session
    public function set_driver($driver)
    {
        $driver = strtolower($driver);

        if ($driver === "file") {
            sc_session_file()->check();
            session_set_save_handler(sc_session_file(), true);
        } elseif ($driver === "database") {
            sc_trigger_error("session driver [database] is not implemented");
        }
        // default：不呼叫 → PHP 原生 session
    }

    // 單值：存入 session
    public function put($name, $value)
    {
        if ($value === null) {
            if (@isset($_SESSION[$name])) {
                unset($_SESSION[$name]);
            }
        } else {
            $_SESSION[$name] = $value;
        }
    }

    // 單值：讀取 session
    public function get($name)
    {
        return (isset($_SESSION[$name]) ? $_SESSION[$name] : null);
    }

    // 分組：存入 session（group.name 結構）
    public function put_group($group, $name, $value)
    {
        if (!@is_array($_SESSION[$group])) {
            $_SESSION[$group] = [];
        }
        $_SESSION[$group][$name] = $value;
    }

    // 分組：讀取 session
    public function get_group($group, $name)
    {
        return (isset($_SESSION[$group][$name]) ? $_SESSION[$group][$name] : null);
    }

    // 刪除 session 鍵
    public function forget($name)
    {
        if (@isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
    }
}
