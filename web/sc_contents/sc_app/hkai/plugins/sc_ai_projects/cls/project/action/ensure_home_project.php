<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\action;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\tab_general_form\repo_update;

// == sc_ai_projects project ensure_home_project：project 域動作（trait——掛入 project_Class；檔＝動作）
// == 職責：確保 Home 專案列存在（slug＝sc_config hkai.default_project_slug·路徑永遠＝<data_dir>/playground）

trait ensure_home_project
{
    // 確保 Home 專案列存在（slug＝home、路徑永遠對齊 playground；缺就建立／舊路徑自動校正）
    public function ensure_home_project()
    {
        $playground_path = sc_get_config("hkai.playground_dir");

        // 判定：沙盒目錄唔存在 → 建立
        if (!is_dir($playground_path)) {
            @mkdir($playground_path, 0777, true);
        }

        // 判定基準：直接查 DB（唔用請求開始時嘅快照——快照未含新資料時會盲插撞 UNIQUE）
        $home_project_rows = sc_db()->select("SELECT * FROM projects WHERE project_slug = ?", [sc_get_config("hkai.default_project_slug")]);
        $home_project_row  = count($home_project_rows) > 0 ? $home_project_rows[0] : null;

        // 判定：Home 已存在 → 路徑一律對齊現行 playground（唔准留住舊資料根）
        if ($home_project_row !== null) {
            $home_project_path = (string)($home_project_row["project_path"] ?? "");

            // 判定：路徑已一致 → 唔做
            if ($home_project_path === $playground_path) {
                return;
            }

            $home_project_id   = (int)($home_project_row["project_id"] ?? 0);

            $home_datapairs                 = [];
            $home_datapairs["project_path"] = $playground_path;

            $project_db = sc_db()->connection("sqlite");

            list($update_sql, $update_bindings) = $project_db->update_sql("projects", $home_datapairs, "project_id = ?", [$home_project_id]);

            $project_db->affectingStatement($update_sql, $update_bindings);

            return;
        }

        // 只填核心欄位（其餘靠 DB default：provider/model/reasoning/tech/git/description/時間）
        $project_datapairs = [];
        $project_datapairs["project_slug"]       = sc_get_config("hkai.default_project_slug");
        $project_datapairs["project_name"]       = "Home";
        $project_datapairs["project_path"]       = $playground_path;
        $project_datapairs["project_sort_index"] = 1;

        // 判定：並行請求（頁面＋AJAX）可能同時見到「Home 未存在」→ 撞唯一鍵即代表另一方已建立
        try {
            repo_update()->insert_project($project_datapairs);
        } catch (\Throwable $insert_error) {
            $is_duplicate_key = false;
            $previous_error   = $insert_error->getPrevious();

            // 判定：底層係 PDOException 且 SQLSTATE 23000 → 唯一鍵衝突
            if ($previous_error instanceof \PDOException) {
                $is_duplicate_key = (string)$previous_error->getCode() === "23000";
            }

            // 判定：唔係唯一鍵衝突 → 原樣拋出（交上層處理）
            if ($is_duplicate_key === false) {
                throw $insert_error;
            }

            // 另一方已建立 → 重跑一次（第二次必走「Home 已存在」分支，順便對齊路徑）
            $this->ensure_home_project();
        }
    }
}
