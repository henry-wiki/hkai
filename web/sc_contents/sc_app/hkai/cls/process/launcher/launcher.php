<?php

namespace sc\app\hkai\cls\process\launcher;

// == launcher_Class：標準 launcher 呼叫（singleton——經 hkai()->process()->launcher() 取得） ==
// == 職責：exec python launcher（services/launcher/main.py——標準 daemon 連接器入口） ==
// == 寫法：呼叫端只傳「有意義嘅嘢」array（第一粒＝daemon 關鍵字；其後＝旗標（--bg／--runtime <path>）） ==
// ==       機械嘢（python 執行檔／-u／-m／模組名）由 launcher 自理；逐粒 escapeshellarg／chdir／exec 統一喺本 class（同 lab run_runner 同形） ==

class launcher_Class
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
    // 例：["bridge", "--bg"]（runtime.json 路徑已由 bridge 自己食死——data/runtime.json）
    // 回 ["ok" => bool, "exit_code" => int, "output" => array]
    // 流程：launcher 身份（<python> -u -m launcher.main——本 class 內建）→ 逐粒轉義 → chdir services → exec → chdir 回
    public function exec($launcher_args)
    {
        // 邊界正規化（Apache FastCGI 會帶 //./ 前綴——子程序 cwd 一污染即失敗）
        // Python 解析：.env（相對站根）→ 產品自帶 execute/python → engine（dev／build）→ 系統 where python
        $python_executable   = "";
        $python_env_setting  = sc_get_env("python_executable", "");
        $repo_root_text      = sc_normalize_path(sc_get_config("repo_root"));

        // 判定：.env 有設定 → 相對路徑以站根為基準
        if ($python_env_setting !== "") {
            $python_env_path = $python_env_setting;

            // 判定：相對路徑（唔係 C:/ 或 UNC //）→ 接上站根（單斜線 / 亦屬站根基準）
            if (preg_match('/^[A-Za-z]:[\\/]|^\\//', $python_env_path) !== 1) {
                $python_env_path = ltrim($python_env_path, "/");
                $python_env_path = $repo_root_text . "/" . $python_env_path;
            }

            $python_env_path = sc_normalize_path($python_env_path);

            // 判定：檔案存在 → 採用
            if (file_exists($python_env_path) === true) {
                $python_executable = $python_env_path;
            }
        }

        // 判定：仍未找到 → 產品候選（execute/python → engine）
        if ($python_executable === "") {
            $python_candidate_list   = [];
            $python_candidate_list[] = dirname(sc_get_config("services")) . "/execute/python313/python.exe";
            $python_candidate_list[] = dirname(sc_get_config("services")) . "/execute/python/python.exe";
            $python_candidate_list[] = dirname(sc_get_config("services"), 2) . "/../engine/python313/python.exe";
            $python_candidate_list[] = dirname(sc_get_config("services"), 3) . "/../engine/python313/python.exe";

            // 逐個候選檢查
            foreach ($python_candidate_list as $python_candidate_item) {
                $python_candidate_normalized = sc_normalize_path($python_candidate_item);

                // 判定：檔案存在 → 採用
                if (file_exists($python_candidate_normalized) === true) {
                    $python_executable = $python_candidate_normalized;
                    break;
                }
            }
        }

        // 判定：仍未找到 → 用系統 where python
        if ($python_executable === "") {
            $where_output_lines = [];
            $where_exit_code    = 0;
            @exec("where python 2>nul", $where_output_lines, $where_exit_code);

            // 判定：有輸出且第一行係檔案 → 採用
            if (count($where_output_lines) > 0) {
                $where_python_path = trim($where_output_lines[0]);

                // 判定：檔案存在 → 採用
                if ($where_python_path !== "" && file_exists($where_python_path) === true) {
                    $python_executable = sc_normalize_path($where_python_path);
                }
            }
        }

        $python_script_dir = sc_normalize_path(sc_get_config("services"));

        // 命令陣列（無 shell 直 CreateProcess——cmd.exe 唔准起 WindowsApps 內嘅 exe，會「拒絕存取」）
        $command_argv   = [];
        $command_argv[] = $python_executable;
        // python 旗標：-u＝不緩衝輸出；-m＝以模組名載入（配下面嘅 launcher.main）
        $command_argv[] = "-u";
        $command_argv[] = "-m";
        $command_argv[] = "launcher.main";

        // 逐粒參數原值放入（無 shell → 唔需轉義）
        foreach ($launcher_args as $launcher_arg_item) {
            $command_argv[] = (string)$launcher_arg_item;
        }

        // 頻道判定所需 host 傳子程序（Python 同 PHP 用同一後綴規則；子程序繼承本 process 環境）
        putenv("HTTP_HOST=" . ($_SERVER["HTTP_HOST"] ?? ""));

        // cwd 交 proc_open（唔用 chdir——免污染本 process）
        $descriptor_spec = [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];
        $pipes   = [];
        $process = proc_open($command_argv, $descriptor_spec, $pipes, $python_script_dir);

        // 判定：開唔到 → 回失敗
        if (is_resource($process) === false) {
            return ["ok" => false, "exit_code" => -1, "output" => ["proc_open failed"]];
        }

        // 收輸出（launcher 輸出細——順序讀已足夠）
        $stdout_text = stream_get_contents($pipes[1]);
        $stderr_text = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit_code = proc_close($process);

        $output_text = trim($stdout_text . $stderr_text);

        $output_lines = [];
        // 判定：有輸出 → 逐行拆
        if ($output_text !== "") {
            $output_lines = preg_split('/\r\n|\r|\n/', $output_text);
        }

        return ["ok" => ($exit_code === 0), "exit_code" => $exit_code, "output" => $output_lines];
    }

}
