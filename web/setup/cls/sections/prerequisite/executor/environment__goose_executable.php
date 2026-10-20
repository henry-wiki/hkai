<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__goose_executable.php（前置檢查「goose 執行檔（開引擎用）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔攞值 → setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：executor()->environment__goose_executable()——外面只係接口，內部成套自足
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__goose_executable_Class
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

    // 檢查（包裝層——零檢查碼：①攞值〔載入時 .env→externals→where〕②叫 setup_test($值) ③原果包裝為 UI 格式）
    // 安裝步驟資料（標題／行值——值由本單元自決：記錄值／未填＝留空字眼）
    public function get_install_step_data()
    {
        $goose_executable_recorded_value = setup()->state()->get_setting_setup_process("prerequisite", "environment__goose_executable");

        $install_step_value = $goose_executable_recorded_value;

        // 判定：未填（空）→ 留空字眼
        if ($install_step_value === "") {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        return ["label" => setup()->i18n()->get("install.step.goose_executable"), "value" => $install_step_value];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.executor.goose_executable.label");

        $goose_env = trim(sc_get_env("goose_executable", ""));

        // 記錄值優先（setup_process.json——用戶測試通過嘅值；冇記錄＝空串）
        $goose_recorded_value = setup()->state()->get_setting_setup_process("prerequisite", "environment__goose_executable");

        // 顯示值（記錄優先——記錄 > .env 現值）
        $goose_display_value = $goose_env;

        // 判定：有記錄 → 記錄優先
        if ($goose_recorded_value !== "") {
            $goose_display_value = $goose_recorded_value;
        }

        // 輸入組＝顯示現值（唯讀；訊息一律入 input_msg）
        $check_result["input"] = [
            "name"        => "environment__goose_executable",
            "value"       => $goose_display_value,
            "readonly"    => true,
            "placeholder" => setup()->i18n()->get("check.executor.goose_executable.placeholder"),
        ];

        // 判定：exec 已停用 → warn（無法探測——①基本檢查已列）
        if (!setup()->is_exec_available()) {
            $check_result["result_content"]["warn"]["hint"] = setup()->i18n()->get("check.executor.hint_probe_disabled");
            return $check_result;
        }

        // 搵本機（externals → PATH——用嚟填值同後備）
        $goose_found = $this->find_machine_goose();

        // 要驗嘅值：.env 有值 → 驗 .env；未設定 → 驗本機搵到嗰個
        $goose_verify_value = "";

        // 判定：.env 有值 → 驗佢
        if ($goose_env !== "") {
            $goose_verify_value = $goose_env;
        }

        // 其餘（.env 未設定）→ 驗本機搵到嗰個
        else {
            $goose_verify_value = $goose_found;
        }

        // 判定：冇值可驗（本機搵唔到）→ warn
        if ($goose_verify_value === "") {
            $check_result["input"]["msg"] = setup()->i18n()->get("check.executor.goose_executable.msg_not_detected");
            return $check_result;
        }

        // 核心檢查＝唯一機制（setup_test——原果）
        $test_result = $this->setup_test($goose_verify_value);

        // ── 原果 → 包裝 UI 格式 ──

        // 判定：驗證通過 → pass
        if ($test_result["ok"] === true) {
            $check_result["status"] = "pass";

            // 判定：.env 未設定（驗嘅係本機搵到嗰個）→ 回填顯示（按「完成」時寫 .env）
            if ($goose_env === "") {
                $check_result["input"]["value"] = $goose_found;

                // 判定：有記錄 → 記錄優先
                if ($goose_recorded_value !== "") {
                    $check_result["input"]["value"] = $goose_recorded_value;
                }
            }

            return $check_result;
        }

        // 其餘（驗證唔過）——分兩支：.env 指壞值／本機偵測值壞

        // 判定：驗嘅係 .env 值 →
        if ($goose_env !== "") {
            // 判定：本機另搵到一個 → warn（有後備——問題較輕）
            if ($goose_found !== "") {
                $check_result["input"]["msg"] = setup()->i18n()->get("check.executor.msg_path_broken");
                return $check_result;
            }

            // 其餘（本機亦冇）→ fail（指咗去死路）
            $check_result["status"] = "fail";
            $check_result["input"]["msg"] = setup()->i18n()->get("check.executor.msg_path_missing");
            return $check_result;
        }

        // 其餘（驗嘅係本機搵到嗰個）→ warn
        $check_result["input"]["msg"] = setup()->i18n()->get("check.executor.msg_detected_path_broken");

        return $check_result;
    }

    // 測試（唯一真實檢查動作——對值跑驗證；回原果 array：{"ok":true}／{"ok":false,"error":…}）
    public function setup_test($input_value)
    {
        // 判定：值空 → 原果 fail
        if ($input_value === "") {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.msg_fill_path_first")];
        }

        $output_lines = [];
        $exit_code    = null;

        @exec("\"" . $input_value . "\" --version 2>&1", $output_lines, $exit_code);

        // 判定：跑唔起 → 原果 fail
        if ($exit_code !== 0) {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.goose_executable.error_path_unusable")];
        }

        $version_text = implode(" ", $output_lines);
        $version_text = trim($version_text);

        // 判定：冇版本輸出（唔係 goose——如其他 exe 照退 0） → 原果 fail
        if ($version_text === "") {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.goose_executable.error_not_executable")];
        }

        // 判定：輸出唔似版號 → 原果 fail
        if (!preg_match("/\d+\.\d+/", $version_text)) {
            return ["ok" => false, "error" => setup()->i18n()->get("check.executor.goose_executable.error_version_format")];
        }

        // 其餘（真跑出檔頭版號）→ 原果 ok
        return ["ok" => true];
    }

    // 搵本機 goose（先 externals〔發佈版標準位〕、後 PATH；搵唔到回空字串）
    private function find_machine_goose()
    {
        $goose_found = "";

        $goose_externals_path = sc_get_config("externals.goose");

        // 判定：externals 有 → 攞
        if (is_file($goose_externals_path)) {
            $goose_found = $goose_externals_path;
        }

        // 判定：externals 冇 → 搵 PATH
        if ($goose_found === "") {
            $goose_where_command = "command -v goose 2>&1";

            // 判定：Windows → 用 where
            if (PHP_OS_FAMILY === "Windows") {
                $goose_where_command = "where goose 2>&1";
            }

            $where_lines = [];
            $where_code  = null;

            @exec($goose_where_command, $where_lines, $where_code);

            // 判定：搵到 → 取首行
            if ($where_code === 0) {
                // 判定：有輸出 → 首行＝路徑
                if (count($where_lines) > 0) {
                    $goose_found = trim($where_lines[0]);
                }
            }
        }

        // 顯示用：斜線統一（全 /）
        $goose_found = str_replace("\\", "/", $goose_found);

        return $goose_found;
    }

    // 安裝（未設定＝偵測本機填入 .env；已有＝保留；偵測唔到＝跳過——回動作原果）
    public function setup_install()
    {
        $goose_env = trim(sc_get_env("goose_executable", ""));

        // 判定：.env 已有值 → 唔覆蓋（保留）
        if ($goose_env !== "") {
            return ["ok" => true, "result" => "kept", "detail" => setup()->i18n()->get("install.detail.kept")];
        }

        // 攞值（同檢查同一機制——本機偵測；值喺 input 回填）
        $check_result = $this->setup_check();

        $goose_value = "";

        // 判定：檢查帶 input 值 → 取用
        if (isset($check_result["input"]["value"])) {
            $goose_value = $check_result["input"]["value"];
        }

        // 判定：冇值可寫（未偵測到）→ 跳過（唔算失敗——前置檢查已列）
        if ($goose_value === "") {
            return ["ok" => true, "result" => "skipped", "detail" => setup()->i18n()->get("install.detail.goose_executable_skipped")];
        }

        // 寫 .env（sc_set_env——缺先加·已有唔覆蓋）
        sc_set_env("goose_executable", $goose_value);

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.written")];
    }
}
