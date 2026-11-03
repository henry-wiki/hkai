<?php

namespace sc\setup\request;

// == request check_migration_source：舊安裝搬遷掃描（trait——掛入 request_Class；檔＝動作）
// == 職責：POST source_dir → 掃描（版本）→ 回 {ok, status, message, version}
// == 只讀：唔寫入、唔建立任何檔

trait check_migration_source
{
    // 掃描舊安裝（唯讀——零寫入）
    public function check_migration_source()
    {
        $source_dir = trim($_POST["source_dir"] ?? "");

        // 判定：空目錄 → fail（搬遷模式必須填）
        if ($source_dir === "") {
            $message = setup()->i18n()->get("check.migration_source.empty");

            $this->respond_json(["ok" => false, "status" => "fail", "message" => $message, "version" => 0], 200);
        }

        $scan_result = setup()->database_migration_source()->scan_source($source_dir);

        // 判定：掃描失敗（目錄不存在／找不到資料庫）→ fail（擋）
        if ($scan_result["ok"] !== true) {
            $message = setup()->i18n()->get($scan_result["detail_key"]);

            $this->respond_json(["ok" => false, "status" => "fail", "message" => $message, "version" => 0], 200);
        }

        $found_text = setup()->i18n()->get("check.migration_source.found");
        $message    = $found_text . " dbv-" . $scan_result["version"];

        $this->respond_json([
            "ok"      => true,
            "status"  => "pass",
            "message" => $message,
            "version" => $scan_result["version"],
        ], 200);
    }
}
