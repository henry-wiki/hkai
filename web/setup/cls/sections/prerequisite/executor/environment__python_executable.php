<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__python_executable.php（前置檢查「Python 執行檔（開引擎用）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝委派 python 域（回原果）；安裝唔涉（.env 寫死——純檢查項）
// == 接入：executor()->environment__python_executable()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__python_executable_Class
{
    private static $instance = null;

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

    // 測試（委派 python 域——對值跑 Python：-c 直出 sys.executable；回原果）
    public function setup_test($input_value)
    {
        return setup()->python()->test_python_command($input_value);
    }

    // 檢查（包裝層——零檢查碼：攞值〔.env→候選〕→叫 setup_test → UI 格式）
    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.executor.python_executable.label");

        // 三態內容（fail——force layout 用；下載連結硬編）
        $check_result["result_content"]["fail"]["detail"] = "https://www.python.org/downloads/";

        // 判定：exec 已停用 → warn（探測唔到——①基本檢查已列）
        if (!setup()->is_exec_available()) {
            $check_result["result_content"]["warn"]["hint"] = setup()->i18n()->get("check.executor.hint_probe_disabled");
            return $check_result;
        }

        // .env 原值（顯示照原值——相對就顯相對）
        $env_python_raw = sc_get_env("python_executable", "");

        // 探測用：相對路徑以站根為基準（同 launcher.php 一致）
        $env_python = setup()->resolve_env_path($env_python_raw);

        // 偵測命令（python 域——候選次序：.env 值優先 → py -3 → python → python3）
        $found_command = setup()->python()->detect_python_command();

        $python_full_path = "";

        // 判定：有命令 → 解析全路徑（-c 直出 sys.executable）
        if ($found_command !== "") {
            $found_test_result = setup()->python()->test_python_command($found_command);

            // 判定：解析唔到（罕有——--version 通但 -c 唔通）→ 歸「搵唔到」（同一果）
            if ($found_test_result["ok"] !== true) {
                $found_command = "";
            }

            // 其餘 → 取全路徑（斜線統一）
            else {
                $python_full_path = str_replace("\\", "/", $found_test_result["resolved_path"]);
            }
        }

        // 記錄值優先（setup_process.json——用戶測試通過嘅值；冇記錄＝空串）
        $python_recorded_value = setup()->state()->get_setting_setup_process("prerequisite", "environment__python_executable");

        // 判定：.env 解析後係真檔案 → 以佢為準（唔靠 exec 探測——安裝路徑含空格）
        if (is_file($env_python)) {
            $found_command    = $env_python;
            $python_full_path = str_replace("\\", "/", $env_python);
        }

        // 判定：全部跑唔起 → fail（未裝——有記錄照顯示記錄值＋問題字）
        if ($found_command === "") {
            $check_result["status"] = "fail";
            $check_result["input"] = [
                "name"  => "environment__python_executable",
                "value" => $python_recorded_value,
                "msg"   => setup()->i18n()->get("check.executor.python_executable.msg_not_detected"),
            ];
            return $check_result;
        }

        // 版本探測（--version——判定引擎需求：3.10 或以上）
        $python_version = setup()->python()->get_python_version($python_full_path);

        $python_version_ok = true;

        // 判定：有抽到版號 → 查夠唔夠新
        if ($python_version !== "") {
            // 判定：低過 3.10 → 標記唔夠新（引擎需要）
            if (version_compare($python_version, "3.10", "<")) {
                $python_version_ok = false;
            }
        }

        // 顯示值（記錄優先——記錄 > .env 原值 > 探測值）
        $python_display_value = $env_python_raw;

        // 判定：冇 .env 原值 → 用探測值
        if ($python_display_value === "") {
            $python_display_value = $env_python;
        }

        // 判定：解析後仍空 → 用探測值
        if ($python_display_value === "") {
            $python_display_value = $python_full_path;
        }

        // 判定：有記錄 → 記錄優先
        if ($python_recorded_value !== "") {
            $python_display_value = $python_recorded_value;
        }

        // 輸入組＝顯示現值（唯讀；訊息一律入 input_msg）
        $check_result["input"] = [
            "name"        => "environment__python_executable",
            "value"       => $python_display_value,
            "readonly"    => true,
            "placeholder" => setup()->i18n()->get("check.executor.python_executable.placeholder"),
        ];

        // 判定：.env 未設定 → 填返 input（exec 搵到嘅全路徑——只回填顯示）
        if ($env_python === "") {
            // 判定：版本夠新 → pass
            if ($python_version_ok) {
                $check_result["status"] = "pass";
                $check_result["input"]["value"] = $python_display_value;
                return $check_result;
            }

            // 其餘（版本過舊）→ warn（填返路徑＋版本提示）
            $check_result["status"] = "warn";
            $check_result["input"]["value"] = $python_display_value;
            $version_msg_prefix = setup()->i18n()->get("check.executor.python_executable.msg_version_prefix");
            $version_msg_suffix = setup()->i18n()->get("check.executor.python_executable.msg_version_suffix");
            $check_result["input"]["msg"] = $version_msg_prefix . $python_version . $version_msg_suffix;
            return $check_result;
        }

        // 判定：.env 有值但同 exec 搵到嘅唔一致（死值）→ warn（問題字入 input_msg）
        if ($found_command !== $env_python) {
            $check_result["status"] = "warn";
            $check_result["input"]["msg"] = setup()->i18n()->get("check.executor.msg_path_broken");
            return $check_result;
        }

        // 判定：版本過舊 → warn（版本提示）
        if (!$python_version_ok) {
            $check_result["status"] = "warn";
            $version_msg_prefix = setup()->i18n()->get("check.executor.python_executable.msg_version_prefix");
            $version_msg_suffix = setup()->i18n()->get("check.executor.python_executable.msg_version_suffix");
            $check_result["input"]["msg"] = $version_msg_prefix . $python_version . $version_msg_suffix;
            return $check_result;
        }

        // 其餘 → pass（.env 路徑已就位）
        $check_result["status"] = "pass";

        return $check_result;
    }
}
