<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\action;

// == sc_ai_projects tab_environment write_project_environment_file：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==
// == 職責：索要專案級偵測（Python 掃描專案路徑）→ 渲染 → 原子寫入 storage 環境檔（系統級唔喺度重複——另有系統環境） ==

trait write_project_environment_file
{
    // 寫專案環境檔（force＝true 強制重寫／false 僅缺檔時寫；偵測失敗＝零寫入；回傳統一結果 ok／error／skipped）
    public function write_project_environment_file($project_row, $is_force_write)
    {
        // 準確值：環境檔＝storage/hkai/environment/env.{slug}.md（app storage——唔寫專案根）
        $runtime_configs = hkai__core()->get_runtime_configs();
        $storage_dir     = $runtime_configs["storage_dir"];
        $env_file        = $storage_dir . "/environment/env." . $project_row["project_slug"] . ".md";

        // 判定：非強制 ＋ 檔已存在 → 跳過（保偵測事實穩定——重新偵測另走手動「更新」掣）
        if ($is_force_write === false) {
            if (is_file($env_file)) {
                return ["ok" => true, "skipped" => true];
            }
        }

        // 向 bridge 索要專案級環境（帶專案完整路徑——Python 掃描該專案；系統級唔喺度重複——另有系統環境）
        $project_path     = $project_row["project_path"];
        $project_env_data = sc_ai_diagnostics()->project_env()->run_project_env_fetch($project_path);

        // 判定：索要失敗（路徑不存在／bridge 未更新）→ 零寫入
        if (($project_env_data["ok"] ?? false) !== true) {
            return ["ok" => false, "error" => $project_env_data["error"] ?? "project env detect failed"];
        }

        // 渲染（專案偵測結果派生——純專案級）
        $markdown_content = $this->render_project_environment_markdown($project_env_data);

        // 判定：environment 目錄不存在 → 遞迴建立（寫檔前搞掂——同 bak 慣例）
        $env_dir = $storage_dir . "/environment";
        if (!is_dir($env_dir)) {
            @mkdir($env_dir, 0777, true);
        }

        $write_result = $this->write_project_environment_file_atomic($env_file, $markdown_content);

        // 判定：寫入失敗（權限等）→ 回錯誤
        if ($write_result === false) {
            return ["ok" => false, "error" => "write environment file failed"];
        }

        return ["ok" => true, "skipped" => false, "detected_at" => $project_env_data["detected_at"] ?? ""];
    }

    // 原子寫檔（先 .tmp 再 rename——讀者永遠見到完整檔；回傳成敗）
    private function write_project_environment_file_atomic($target_file, $file_content)
    {
        $temp_file = $target_file . ".tmp";

        // 判定：tmp 寫入失敗 → false（不動目標檔）
        if (@file_put_contents($temp_file, $file_content, LOCK_EX) === false) {
            return false;
        }

        // 判定：rename 失敗 → 清理 tmp、false
        if (!@rename($temp_file, $target_file)) {
            @unlink($temp_file);
            return false;
        }

        return true;
    }
}
