<?php

namespace sc\setup\request;

// == request validate_section：下一步前儲存＋驗證（trait——掛入 request_Class；檔＝動作）
// == 職責：POST section＋formdata → 先記入過程檔 → 按格驗證 → 回 {ok:true} 或 {ok:false, error, field}
// == 上游：前端「下一步」掣（evt/switch_section）
// == 下游：record_section::save_formdata_values（共用儲存）、database_migration_source（搬遷掃描）

trait validate_section
{
    // 儲存＋驗證（後台唯一裁決——前端只顯示結果）
    public function validate_section()
    {
        $section_id      = trim($_POST["section"] ?? "");
        $posted_formdata = $_POST["formdata"] ?? [];

        // 先儲存（用戶所選）
        $this->save_formdata_values($posted_formdata);

        $validate_result = $this->validate_section_values($section_id, $posted_formdata);

        // 判定：通過且搬遷模式 → 即時複製來源到暫存（Settings／Install 之後只讀暫存）
        if ($validate_result["ok"] === true) {
            $this->prepare_import_staging();
        }

        $this->respond_json($validate_result, 200);
    }

    // 建暫存（搬遷模式且來源有效；key＝來源路徑 md5——同一來源重用同一暫存）
    private function prepare_import_staging()
    {
        $source_mode = setup()->state()->get_setting_setup_process("welcome", "source_mode");

        // 判定：非搬遷 → 唔做
        if ($source_mode !== "import") {
            return;
        }

        $source_dir = setup()->state()->get_setting_setup_process("welcome", "source_dir");

        // 判定：冇來源 → 唔做
        if ($source_dir === "") {
            return;
        }

        $staging_key = md5($source_dir);

        setup()->staging()->prepare($staging_key, $source_dir);
    }

    // 逐格驗證（回 {ok:true}／{ok:false, error, field}）
    private function validate_section_values($section_id, $posted_formdata)
    {
        // 判定：welcome 格 → 搬遷模式必須有有效舊安裝
        if ($section_id === "welcome") {
            return $this->validate_welcome_values($posted_formdata);
        }

        // 判定：upgrade_review 格 → 重新掃描來源（防中途被移走）
        if ($section_id === "upgrade_review") {
            return $this->validate_welcome_values($posted_formdata);
        }

        return ["ok" => true];
    }

    // welcome 格驗證（搬遷模式：目錄必須存在＋找到資料庫＋讀到版本）
    private function validate_welcome_values($posted_formdata)
    {
        // 值以本次提交為準（狀態檔可能未寫入）；缺項退回狀態檔
        $source_mode = trim($posted_formdata["welcome"]["source_mode"]["value"] ?? "");
        $source_dir  = trim($posted_formdata["welcome"]["source_dir"]["value"] ?? "");

        // 判定：提交冇帶模式 → 用狀態檔
        if ($source_mode === "") {
            $source_mode = setup()->state()->get_setting_setup_process("welcome", "source_mode");
        }

        // 判定：提交冇帶目錄 → 用狀態檔
        if ($source_dir === "") {
            $source_dir = setup()->state()->get_setting_setup_process("welcome", "source_dir");
        }

        // 判定：全新安裝 → 放行
        if ($source_mode !== "import") {
            return ["ok" => true];
        }

        // 判定：目錄空 → 擋
        if ($source_dir === "") {
            $error_message = setup()->i18n()->get("check.migration_source.empty");

            return ["ok" => false, "error" => $error_message, "field" => "source_dir"];
        }

        $scan_result = setup()->database_migration_source()->scan_source($source_dir);

        // 判定：掃描失敗 → 擋（交 i18n 訊息）
        if ($scan_result["ok"] !== true) {
            $error_message = setup()->i18n()->get($scan_result["detail_key"]);

            return ["ok" => false, "error" => $error_message, "field" => "source_dir"];
        }

        return ["ok" => true];
    }
}
