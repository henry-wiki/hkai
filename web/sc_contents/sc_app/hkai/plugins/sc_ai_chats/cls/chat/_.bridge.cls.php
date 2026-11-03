<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat;

// == sc_ai_chats chat\chat_Class（bridge 檔）：chat 域本體（singleton——bridging sc_ai_chats()->chat()） ==
// == 動作（cls/chat/ 各 trait——一檔一動作） ==
// == 2026-09-27 六個 chat class（history／init／repo_select／repo_update／resolver／view）合成單一本體 ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class chat_Class
{
    use action\cleanup_old_logs;
    use init\get_chat_sid;
    use repo_select\get_chat_rows;
    use repo_select\get_latest_chat_row_by_project;
    use repo_select\get_project_chat_stats;
    use repo_select\get_project_delete_stats;
    use repo_select\is_chat_empty;
    use repo_update\insert_db_chat;
    use repo_update\delete_db_chat;
    use repo_update\archive_db_chat;
    use repo_update\restore_db_chat;
    use resolver\resolve_selected_chat_sid;
    use resolver\get_latest_chat_sid;
    use resolver\is_archive_chat_segment;
    use resolver\resolve_chat_sid;
    use resolver\is_valid_chat_sid;
    use resolver\resolve_chat_row;
    use view\render_chat_item;
    use view\get_chat_item_html;
    use view\render_chat_list;
    use view\render_chat_sidebar;
    use request\chat_item_fetch;
    use request\chat_list_fetch;
    use request\handle_chat_delete;
    use request\handle_chat_archive;
    use request\handle_chat_restore;

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


    // 對話段（{chat_sid}；歸檔＝語境形 archive@{chat_sid}——段知識唯一住處；卡片身份 attr 同 URL 共用）
    public function build_chat_segment($chat_sid, $chat_status = "normal")
    {
        // 判定：已歸檔對話 → 語境形（archive@ 前綴——第一個 @ 分界）
        if ($chat_status === "archived") {
            return "archive@" . $chat_sid;
        }

        return $chat_sid;
    }

    // 對話 URL（{project_segment}/{chat_segment}/——URL 知識唯一住處：回應與清單連結共用；前端唔自組）
    // 兩段各跟各域 builder：第一段專案段（歸檔形 archive@<project_slug>——projects 域出）、第二段對話段（歸檔形 archive@<chat_sid>）
    public function build_chat_url($project_slug, $project_status, $chat_sid, $chat_status = "normal")
    {
        $project_segment = sc_ai_projects()->project()->build_project_segment($project_slug, $project_status);

        // 判定：無專案（home／project_id 0）→ URL 用 home 段
        if ($project_segment === "" || $project_segment === null) {
            $project_segment = sc_get_config("hkai.default_project_slug");
        }

        return "/" . $project_segment . "/" . $this->build_chat_segment($chat_sid, $chat_status) . "/";
    }




    // 是否有選取專案（空狀態顯隱判定——兩處輸出共用）
    public function is_project_selected()
    {
        $selected_project_slug = sc_ai_projects()->project()->get_project_slug();
        
        return (string)$selected_project_slug !== "";
    }


}
