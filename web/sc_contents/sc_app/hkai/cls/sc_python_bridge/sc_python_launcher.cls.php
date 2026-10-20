<?php

namespace sc\app\hkai\cls\sc_python_bridge;

// == sc_python_launcher_Class：標準 launcher 呼叫（singleton——經 bridging hkai__python_launcher() 取得） ==
// == 職責：exec python launcher（services/launcher/main.py——標準 daemon 連接器入口） ==
// == 寫法：呼叫端只傳「有意義嘅嘢」array（第一粒＝daemon 關鍵字；其後＝旗標（--bg／--runtime <path>）） ==
// ==       機械嘢（python 執行檔／-u／-m／模組名）由 launcher 自理；逐粒 escapeshellarg／chdir／exec 統一喺本 class（同 lab run_runner 同形） ==

class sc_python_launcher_Class
{
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

    // exec python launcher（$launcher_args＝有意義嘅參數 array——機械嘢唔喺度傳）
    // 例：["bridge", "--bg", "--runtime", "<runtime.json>"]
    // 回 ["ok" => bool, "exit_code" => int, "output" => array]
    // 流程：launcher 身份（<python> -u -m launcher.main——本 class 內建）→ 逐粒轉義 → chdir services → exec → chdir 回
    public function exec($launcher_args)
    {
        $python_executable = hkai__core()->get_python_executable();
        $python_script_dir = hkai__core()->get_python_script_dir();

        $command_parts = [];
        $command_parts[] = escapeshellarg($python_executable);
        // python 旗標：-u＝不緩衝輸出；-m＝以模組名載入（配下面嘅 launcher.main）
        $command_parts[] = "-u";
        $command_parts[] = "-m";
        $command_parts[] = "launcher.main";

        // 逐粒參數原值轉義（引號喺呢度先落——呼叫端唔砌字串；同 lab run_runner 同形）
        foreach ($launcher_args as $launcher_arg_item) {
            $command_parts[] = escapeshellarg((string)$launcher_arg_item);
        }

        $command = implode(" ", $command_parts);

        $previous_cwd = getcwd();
        chdir($python_script_dir);

        $output_lines = [];
        $exit_code    = 0;
        exec($command . " 2>&1", $output_lines, $exit_code);

        chdir($previous_cwd);

        return ["ok" => ($exit_code === 0), "exit_code" => $exit_code, "output" => $output_lines];
    }
}
