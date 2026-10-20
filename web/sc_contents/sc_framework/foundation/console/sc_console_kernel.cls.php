<?php
namespace sc\framework\foundation\console;

// == sc_console_kernel：Console Kernel（CLI 入口——對應 Laravel Illuminate\Foundation\Console\Kernel） ==
// == 用法：console/xxx.php → require configs + bootstrap → $app->make("console_kernel")->run($argv) ==
// == 原則：啟動共用（與 HTTP 同走 kernel::bootstrap 引導）＋分發分型（CLI 不分發 HTTP——直接執行命令） ==

class sc_console_kernel_Class
{
    private static $instance = null;
    
    // 命令註冊表（對應 Laravel routes/console.php——命令名 → 命令檔路徑）
    private $commands = [];
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
    }
    
    // 註冊命令（對應 Laravel Artisan::command——由 sc_routes/console.php 呼叫）
    // 用法：sc_console_command("status", SC_DOCUMENT_ROOT . "/console/commands/status.php")
    public function register_command($commandName, $commandFile)
    {
        $this->commands[$commandName] = $commandFile;
    }
    
    // 執行命令列請求（對應 Laravel Console Kernel::handle——artisan 入口）
    // 用法：$kernel->run($argv)——$argv = PHP 命令列參數（console/xxx.php 傳入）
    // 流程：引導（與 HTTP 共用 kernel::bootstrap）→ 載入命令註冊表 → 依第一參數分發命令
    public function run($argv = null)
    {
        // 未傳入 → 取命令列全域
        if ($argv === null) {
            // 判定：非 CLI 環境冇 argv → 空陣列
            $argv = isset($_SERVER["argv"]) ? $_SERVER["argv"] : [];
        }
        
        // 引導（與 HTTP 同款——載入元件/註冊/初始化；CLI 不分發 HTTP）
        sc_kernel()->bootstrap();
        
        // 載入命令註冊表（sc_routes/console.php——集中宣告命令名 → 命令檔）
        $consoleRoutesFile = SC_DIR_CONTENTS . "/sc_routes/console.php";
        if (file_exists($consoleRoutesFile)) {
            require_once $consoleRoutesFile;
        }
        
        // 剖析命令：第一參數 = 命令名（如 status/clear-cache）
        $commandName = isset($argv[1]) ? $argv[1] : null;
        
        // 無命令 → 列出可用命令提示
        if (!$commandName) {
            echo "hkai console\n";
            echo "可用命令：\n";
            foreach ($this->commands as $registeredName => $registeredFile) {
                echo "  " . $registeredName . "\n";
            }
            return;
        }
        
        // 查命令註冊表（先查集中註冊表）
        $commandFile = isset($this->commands[$commandName]) ? $this->commands[$commandName] : null;
        
        // 未命中 → 備援：慣例路徑（console/commands/{command}.php）
        if (!$commandFile) {
            $fallbackFile = SC_DOCUMENT_ROOT . "/console/commands/" . $commandName . ".php";
            if (file_exists($fallbackFile)) {
                $commandFile = $fallbackFile;
            }
        }
        
        // 命令檔不存在 → 提示
        if (!$commandFile || !file_exists($commandFile)) {
            echo "未知命令：[" . $commandName . "]\n";
            return;
        }
        
        require $commandFile;
    }
}
