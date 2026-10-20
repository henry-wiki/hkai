<?php

namespace sc\setup\python;

// == HKAI setup python：python 域本體（域本體——singleton；呼叫形 setup()->python()->detect_python_command()）
// == 內容：class（singleton）＋全部 Python 相關操作——執行命令偵測／執行檔測試／版本讀取／pip 探測／套件探測／aider 套件位置
// == 規格：零框架（唔用 autoloader／session／DB——只用 PHP 內建 exec）；語法下限 PHP 5.4

class python_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\python\python_Class
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 偵測 Python 執行命令（候選次序：.env 指定 → py -3 → python → python3；搵唔到＝空字串）
    public function detect_python_command()
    {
        $python_executable = trim(sc_get_env("python_executable", ""));

        $candidate_commands = [];

        // 判定：.env 有指定 python_executable → 排最前
        if ($python_executable !== "") {
            $candidate_commands[] = $python_executable;
        }

        $candidate_commands[] = "py -3";
        $candidate_commands[] = "python";
        $candidate_commands[] = "python3";

        // 逐個候選試（--version 成功＝搵到）
        foreach ($candidate_commands as $candidate_command_item) {
            // 判定：exec 已停用 → 放棄探測（唔強行跑）
            if (!setup()->is_exec_available()) {
                return "";
            }

            $output_lines = [];
            $exit_code    = null;

            @exec($candidate_command_item . " --version 2>&1", $output_lines, $exit_code);

            // 判定：成功 → 回呢個命令
            if ($exit_code === 0) {
                return $candidate_command_item;
            }
        }

        return "";
    }

    // 測試（唯一真實檢查動作——對值跑 Python：-c 直出 sys.executable；回原果）
    public function test_python_command($python_command)
    {
        // 判定：值空 → 原果 fail
        if ($python_command === "") {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.msg_fill_path_first")];
        }

        $output_lines = [];
        $exit_code    = null;

        @exec($python_command . " -c \"import sys; print(sys.executable)\" 2>&1", $output_lines, $exit_code);

        // 判定：跑唔起 → 原果 fail
        if ($exit_code !== 0) {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.python_executable.error_path_unusable")];
        }

        // 判定：冇輸出（唔係 Python——如啟動器 exe 照退 0） → 原果 fail
        if (count($output_lines) === 0) {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.python_executable.error_not_executable")];
        }

        $resolved_path = trim($output_lines[0]);

        // 判定：輸出唔係真檔案 → 原果 fail
        if (!is_file($resolved_path)) {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.python_executable.error_not_real_path")];
        }

        // 其餘 → 原果 ok（附解析路徑——候選掃描要用）
        return ["ok" => true, "resolved_path" => $resolved_path];
    }

    // 讀 Python 版本（--version 輸出抽數字版號；抽唔到＝空字串）
    public function get_python_version($python_command)
    {
        $version_output_lines = [];
        $version_exit_code    = null;

        @exec("\"" . $python_command . "\" --version 2>&1", $version_output_lines, $version_exit_code);

        $python_version = "";

        // 判定：有輸出 → 抽出數字版號
        if (count($version_output_lines) > 0) {
            $version_line_text = trim($version_output_lines[0]);

            // 判定：抽到版號 → 取用
            if (preg_match("/(\d+\.\d+\.\d+|\d+\.\d+)/", $version_line_text, $version_matches)) {
                $python_version = $version_matches[1];
            }
        }

        return $python_version;
    }

    // 探測 pip（python -m pip --version；回原果——warn 帶處置提示）
    public function check_pip()
    {
        // 判定：exec 已停用 → 原果 warn（探測唔到——「PHP 函數」組已列）
        if (!setup()->is_exec_available()) {
            return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.python.hint_probe_disabled"), "detail" => "", "reading" => ""];
        }

        $python_command = $this->detect_python_command();

        // 判定：Python 未就緒 → 原果 warn（交「Python 執行檔」一項處理）
        if ($python_command === "") {
            return ["ok" => false, "status" => "warn", "hint" => "", "detail" => setup()->i18n()->get("check.python.msg_python_not_ready"), "reading" => ""];
        }

        $output_lines = [];
        $exit_code    = null;

        @exec($python_command . " -m pip --version 2>&1", $output_lines, $exit_code);

        // 判定：跑得起 → 原果 pass
        if ($exit_code === 0) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => ""];
        }

        // 其餘 → 原果 warn（提醒——安裝依賴時需要）
        return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.python_packages.hint_pip_missing"), "detail" => "", "reading" => ""];
    }

    // 探測 Python 套件（fastapi／uvicorn／psutil 三件套；回原果）
    public function check_python_packages()
    {
        $python_command = $this->detect_python_command();

        // 判定：Python 未就緒 → 原果 warn（交「Python 執行檔」一項處理）
        if ($python_command === "") {
            return ["ok" => false, "status" => "warn", "hint" => "", "detail" => setup()->i18n()->get("check.python.msg_python_not_ready"), "reading" => ""];
        }

        $output_lines = [];
        $exit_code    = null;

        @exec($python_command . ' -c "import fastapi, uvicorn, psutil" 2>&1', $output_lines, $exit_code);

        // 判定：import 成功 → 原果 pass（三件套齊）
        if ($exit_code === 0) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => setup()->i18n()->get("check.python_packages.detail_all_ready"), "reading" => ""];
        }

        $error_line = "";

        // 判定：有錯誤輸出 → 取首行做詳情
        if (count($output_lines) > 0) {
            $error_line = trim($output_lines[0]);
        }

        // 其餘 → 原果 fail（缺套件——按提示安裝）
        return ["ok" => false, "status" => "fail", "hint" => "pip install fastapi uvicorn psutil", "detail" => setup()->i18n()->get("check.python_packages.detail_missing_prefix") . $error_line, "reading" => ""];
    }

    // 搵本機 aider 套件位置（先 externals〔發佈版標準位〕、後 python import 位置；搵唔到回空字串）
    public function find_aider_pylibs_dir()
    {
        $aider_found = "";

        $aider_externals_path = sc_get_config("externals.aider");

        // 判定：externals 有 → 攞（發佈版標準位）
        if (is_dir($aider_externals_path)) {
            $aider_found = $aider_externals_path;
        }

        // 判定：externals 冇 → 問 python（import aider 嘅實際位置）
        if ($aider_found === "") {
            $python_command = $this->detect_python_command();

            // 判定：有 python → 試匯入
            if ($python_command !== "") {
                $import_lines = [];
                $import_code  = null;

                @exec($python_command . " -c \"import aider, os; print(os.path.dirname(os.path.dirname(aider.__file__)))\" 2>&1", $import_lines, $import_code);

                // 判定：匯入成功 → 睇輸出
                if ($import_code === 0) {
                    // 判定：有輸出 → 取尾行（print 輸出——警告可能喺前面）
                    if (count($import_lines) > 0) {
                        $last_import_index = count($import_lines) - 1;
                        $aider_found       = trim($import_lines[$last_import_index]);
                    }
                }
            }
        }

        // 顯示用：斜線統一（全 /）
        $aider_found = str_replace("\\", "/", $aider_found);

        return $aider_found;
    }
}
