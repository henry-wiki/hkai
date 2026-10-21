<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project;

// == sc_ai_projects project：project 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->project()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat class 合成） ==

class project_Class
{
    use action\ensure_home_project;
    use init\get_initial_data;
    use init\get_selected_project_slug;
    use init\has_project;
    use init\initial;
    use repo_select\get_archived_project_rows;
    use repo_select\get_project_row_by_path;
    use repo_select\get_project_rows;
    use repo_update\archive_project;
    use repo_update\delete_project;
    use repo_update\insert_project;
    use repo_update\refresh_project_stats;
    use repo_update\restore_project;
    use repo_update\update_project;
    use request\handle_project_restore;
    use resolver\get_default_project;
    use resolver\normalize_project_path;
    use resolver\get_bare_project_slug;
    use resolver\is_archive_project_key;
    use slug\add_reserved_slugs;
    use slug\generate_project_slug;
    use slug\is_reserved_slug;
    use slug\is_slug_taken;
    use view\project_list_fetch;
    use view\project_item_fetch;
    use view\render_archived_project_list;
    use view\render_project_item;
    use view\render_project_list;
    use view\render_project_sidebar;

    private $initial_data = [];
    private $is_initial;
    private $reserved_slugs = [];

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {
    }

    // 專案段（{project_slug}；歸檔＝語境形 archive@{project_slug}——段知識唯一住處；卡片連結同 URL 共用）
    public function build_project_segment($project_slug, $project_status = "normal")
    {
        // 判定：已歸檔專案 → 語境形（archive@ 前綴——第一個 @ 分界）
        if ($project_status === "archived") {
            return "archive@" . $project_slug;
        }

        return $project_slug;
    }

    // 專案 URL（/{project_segment}/——URL 知識唯一住處：清單連結用；前端唔自組）
    public function build_project_url($project_slug, $project_status = "normal")
    {
        return "/" . $this->build_project_segment($project_slug, $project_status) . "/";
    }
}
