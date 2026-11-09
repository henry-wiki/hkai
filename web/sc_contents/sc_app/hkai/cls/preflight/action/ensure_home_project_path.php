<?php

namespace sc\app\hkai\cls\preflight\action;

// == ensure_home_project_path_Class：確保 Home 專案路徑＝[data_dir]/playground（每次檢查，唔對即修）
// == 上游：preflight_Class::run()
// == 下游：sc_ai_projects()->project()->ensure_home_project()（修正本體——同一份邏輯，唔重複實作）

class ensure_home_project_path_Class
{
    // 跑一條檢查（回 1＝有修正、0＝無事）
    public function run()
    {
        $playground_path  = sc_get_config("hkai.playground_dir");
        $home_project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));

        // 判定：Home 未建立 → 交 ensure_home_project 建立（建立唔算「修正」）
        if ($home_project_row === null) {
            sc_ai_projects()->project()->ensure_home_project();

            return 0;
        }

        $home_project_path = (string)($home_project_row["project_path"] ?? "");

        // 判定：路徑已一致 → 無事
        if ($home_project_path === $playground_path) {
            return 0;
        }

        sc_ai_projects()->project()->ensure_home_project();

        $fix_message = "preflight: home project_path " . $home_project_path . " → " . $playground_path;

        hkai()->runtime()->logger()->info($fix_message);

        return 1;
    }
}
