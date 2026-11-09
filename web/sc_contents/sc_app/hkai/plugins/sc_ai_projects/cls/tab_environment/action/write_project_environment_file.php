<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\action;

// == sc_ai_projects tab_environment write_project_environment_file：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==
// == 職責：索要專案級偵測（Python 掃描專案路徑）→ 渲染 → 原子寫入 storage 環境檔（系統級唔喺度重複——另有系統環境） ==

trait write_project_environment_file
{
    // 寫專案環境檔（force＝true 強制重掃重寫／false＝只補缺檔或核對 runtime facts；偵測失敗＝零寫入；回傳統一結果 ok／error／skipped）
    public function write_project_environment_file($project_row, $is_force_write)
    {
        // 準確值：環境檔＝storage/hkai/environment/env.{slug}.md（app storage——唔寫專案根）
        $storage_dir     = sc_get_data_dir();
        $env_file        = $storage_dir . "/environment/env." . $project_row["project_slug"] . ".md";

        // 專案固定事實（runtime facts 用——agent 唔靠猜）
        $project_path    = $project_row["project_path"];
        $project_slug    = $project_row["project_slug"];
        $goose_path_root = $storage_dir . "/localdb/goose/" . $project_slug;

        $runtime_fact_lines   = [];
        $runtime_fact_lines[] = "## HKAI runtime facts（自動注入·勿手改）";
        $runtime_fact_lines[] = "";
        $runtime_fact_lines[] = "- GOOSE_PATH_ROOT: " . $goose_path_root;
        $runtime_fact_lines[] = "- goose config: " . $goose_path_root . "/config/config.yaml";
        $runtime_fact_lines[] = "- project_slug: " . $project_slug;
        $runtime_fact_lines[] = "- project_path: " . $project_path;

        $runtime_facts_markdown = implode(PHP_EOL, $runtime_fact_lines);

        // 判定：非強制 ＋ 檔已存在 → 只核對 runtime facts（零掃描；相同＝零寫入）
        if ($is_force_write === false) {
            if (is_file($env_file) === true) {
                $existing_markdown = file_get_contents($env_file);

                // 判定：檔有內容 → 核對／補 facts（空檔＝照走全量重生）
                if (trim($existing_markdown) !== "") {
                    // 判定：facts 段完全一致 → 跳過
                    if (strpos($existing_markdown, $runtime_facts_markdown) !== false) {
                        return ["ok" => true, "skipped" => true];
                    }

                    // 去掉舊 facts 段（由標題到檔尾）再補新版
                    $facts_position = strpos($existing_markdown, "## HKAI runtime facts");

                    // 判定：有舊 facts 段 → 截走（保留前面偵測內容）
                    if ($facts_position !== false) {
                        $existing_markdown = substr($existing_markdown, 0, $facts_position);
                    }

                    $refreshed_markdown = rtrim($existing_markdown) . PHP_EOL . PHP_EOL . $runtime_facts_markdown;

                    $write_result = $this->write_project_environment_file_atomic($env_file, $refreshed_markdown);

                    // 判定：寫入失敗 → 回錯誤
                    if ($write_result === false) {
                        return ["ok" => false, "error" => "write environment file failed"];
                    }

                    return ["ok" => true, "skipped" => false, "runtime_facts_refreshed" => true];
                }
            }
        }

        // 向 bridge 索要專案級環境（帶專案完整路徑——Python 掃描該專案；系統級唔喺度重複——另有系統環境）
        $project_env_data = sc_ai_diagnostics()->project_env()->run_project_env_fetch($project_path);

        // 判定：索要失敗（路徑不存在／bridge 未更新）→ 零寫入
        if (($project_env_data["ok"] ?? false) !== true) {
            return ["ok" => false, "error" => $project_env_data["error"] ?? "project env detect failed"];
        }

        // 渲染（專案偵測結果派生——純專案級）
        $markdown_content = $this->render_project_environment_markdown($project_env_data);

        $markdown_content .= PHP_EOL . PHP_EOL . $runtime_facts_markdown;

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
