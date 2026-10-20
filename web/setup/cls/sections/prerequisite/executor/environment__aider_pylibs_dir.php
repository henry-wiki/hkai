<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__aider_pylibs_dir.php（前置檢查「aider 套件目錄（開引擎用）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：executor()->environment__aider_pylibs_dir()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__aider_pylibs_dir_Class
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

    // 檢查（包裝層——零檢查碼：攞值〔.env／本機探測〕→叫 setup_test → UI 格式）
    // 安裝步驟資料（標題／行值——值由本單元自決：記錄值／未填＝留空字眼）
    public function get_install_step_data()
    {
        $aider_pylibs_dir_recorded_value = setup()->state()->get_setting_setup_process("prerequisite", "environment__aider_pylibs_dir");

        $install_step_value = $aider_pylibs_dir_recorded_value;

        // 判定：未填（空）→ 留空字眼
        if ($install_step_value === "") {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        return ["label" => setup()->i18n()->get("install.step.aider_pylibs_dir"), "value" => $install_step_value];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.executor.aider_pylibs_dir.label");

        $aider_env = trim(sc_get_env("aider_pylibs_dir", ""));

        // 記錄值優先（setup_process.json——用戶測試通過嘅值；冇記錄＝空串）
        $aider_recorded_value = setup()->state()->get_setting_setup_process("prerequisite", "environment__aider_pylibs_dir");

        // 顯示值（記錄優先——記錄 > .env 現值）
        $aider_display_value = $aider_env;

        // 判定：有記錄 → 記錄優先
        if ($aider_recorded_value !== "") {
            $aider_display_value = $aider_recorded_value;
        }

        // 輸入組＝顯示現值（唯讀；訊息一律入 input_msg）
        // 更新掣開關（唯讀顯示值——唔設更新掣）
        $check_result["input"] = [
            "name"        => "environment__aider_pylibs_dir",
            "value"       => $aider_display_value,
            "readonly"    => true,
            "placeholder" => setup()->i18n()->get("check.executor.aider_pylibs_dir.placeholder"),
        ];

        // 判定：exec 已停用 → warn（無法探測——①基本檢查已列）
        if (!setup()->is_exec_available()) {
            $check_result["result_content"]["warn"]["hint"] = setup()->i18n()->get("check.executor.hint_probe_disabled");
            return $check_result;
        }

        // 搵本機（externals → python import 位置——用嚟填值同後備）
        $aider_found = setup()->python()->find_aider_pylibs_dir();

        // 要驗嘅值：.env 有值 → 驗 .env；未設定 → 驗本機搵到嗰個
        $aider_verify_value = "";

        // 判定：.env 有值 → 驗佢
        if ($aider_env !== "") {
            $aider_verify_value = $aider_env;
        }

        // 其餘（.env 未設定）→ 驗本機搵到嗰個
        else {
            $aider_verify_value = $aider_found;
        }

        // 判定：冇值可驗（本機搵唔到）→ warn
        if ($aider_verify_value === "") {
            $check_result["input"]["msg"] = setup()->i18n()->get("check.executor.aider_pylibs_dir.msg_not_detected");
            return $check_result;
        }

        // 核心檢查＝唯一機制（setup_test——原果）
        $test_result = $this->setup_test($aider_verify_value);

        // 判定：驗證通過 → pass
        if ($test_result["ok"] === true) {
            $check_result["status"] = "pass";

            // 判定：.env 未設定（驗嘅係本機搵到嗰個）→ 回填顯示（按「完成」時寫 .env）
            if ($aider_env === "") {
                $check_result["input"]["value"] = $aider_found;

                // 判定：有記錄 → 記錄優先
                if ($aider_recorded_value !== "") {
                    $check_result["input"]["value"] = $aider_recorded_value;
                }
            }

            return $check_result;
        }

        // 其餘（驗證唔過）——分兩支：.env 指壞值／本機偵測值壞

        // 判定：驗嘅係 .env 值 →
        if ($aider_env !== "") {
            // 判定：本機另搵到一個 → warn（有後備——問題較輕）
            if ($aider_found !== "") {
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

    // 測試（唯一真實檢查動作——留空＝默認套件；有值＝目錄存在？回原果）
    public function setup_test($input_value)
    {
        // 判定：值空 → 原果 ok＋note（留空＝使用默認途徑——唔驗）
        if ($input_value === "") {
            return ["ok" => true, "note" => setup()->i18n()->get("check.executor.aider_pylibs_dir.note_default")];
        }

        // 判定：目錄存在 → 原果 ok
        if (is_dir($input_value)) {
            return ["ok" => true];
        }

        // 其餘（目錄唔存在）→ 原果 fail
        return ["ok" => false, "error" => setup()->i18n()->get("check.executor.aider_pylibs_dir.error_dir_missing")];
    }


    // 安裝（未設定＝偵測本機填入 .env；已有＝保留；偵測唔到＝跳過——回動作原果）
    public function setup_install()
    {
        $aider_env = trim(sc_get_env("aider_pylibs_dir", ""));

        // 判定：.env 已有值 → 唔覆蓋（保留）
        if ($aider_env !== "") {
            return ["ok" => true, "result" => "kept", "detail" => setup()->i18n()->get("install.detail.kept")];
        }

        // 攞值（同檢查同一機制——本機偵測；值喺 input 回填）
        $check_result = $this->setup_check();

        $aider_value = "";

        // 判定：檢查帶 input 值 → 取用
        if (isset($check_result["input"]["value"])) {
            $aider_value = $check_result["input"]["value"];
        }

        // 判定：冇值可寫（未偵測到）→ 跳過（唔算失敗——前置檢查已列）
        if ($aider_value === "") {
            return ["ok" => true, "result" => "skipped", "detail" => setup()->i18n()->get("install.detail.aider_pylibs_dir_skipped")];
        }

        // 寫 .env（sc_set_env——缺先加·已有唔覆蓋）
        sc_set_env("aider_pylibs_dir", $aider_value);

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.written")];
    }
}
