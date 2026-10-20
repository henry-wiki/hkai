<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\system_env\action;

// == sc_ai_diagnostics system_env write_system_env_file：system_env 域動作（trait——掛入 system_env_Class；檔＝動作） ==
// == 職責：索要 → 寫一檔（sc_config/hkai/instructions/system_env.md——系統環境 instructions，給 AI 讀） ==
// == 寫入一律 atomic（tmp → rename）；偵測失敗（bridge 未運行／ok=false）→ 零寫入，回錯誤 ==

trait write_system_env_file
{
    // 執行偵測並寫入（force＝true 強制重寫／false 僅在缺檔時寫；回傳統一結果 ok／error）
    public function write_system_env_file($is_force_write)
    {
        $env_paths = $this->get_system_env_paths();

        // 判定：非強制 ＋ 單檔齊備 → 跳過（保偵測事實穩定——更新另走手動）
        if ($is_force_write === false) {
            if ($this->check_system_env_file_exist()) {
                return ["ok" => true, "skipped" => true];
            }
        }

        $env_data = $this->run_system_env_fetch();

        // 判定：索要失敗（bridge 未運行／HTTP 錯誤）→ 零寫入
        if (($env_data["ok"] ?? false) !== true) {
            return ["ok" => false, "error" => $env_data["error"] ?? "system env detect failed"];
        }

        // 判定：manifest 目錄不存在 → 建立（instructions 子目錄一齊補）
        if (!is_dir($env_paths["manifest_dir"])) {
            @mkdir($env_paths["manifest_dir"], 0777, true);
        }
        if (!is_dir(dirname($env_paths["markdown_file"]))) {
            @mkdir(dirname($env_paths["markdown_file"]), 0777, true);
        }

        // 寫 Markdown（渲染層——由同一份偵測資料派生）
        $markdown_content = $this->render_system_env_markdown($env_data);
        $this->write_file_atomic($env_paths["markdown_file"], $markdown_content);

        return ["ok" => true, "skipped" => false, "detected_at" => $env_data["detected_at"] ?? ""];
    }
}
