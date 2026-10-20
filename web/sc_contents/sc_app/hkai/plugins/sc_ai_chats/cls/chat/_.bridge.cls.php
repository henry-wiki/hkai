<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat;

// == sc_ai_chats chat\chat_Class（bridge 檔）：chat 域本體（singleton——bridging sc_ai_chats()->chat()） ==
// == 動作（cls/chat/ 各 trait——一檔一動作） ==
// == 2026-09-27 六個 chat class（history／init／repo_select／repo_update／resolver／view）合成單一本體 ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class chat_Class
{
    use history\chat_history_update;
    use history\chat_history_delete;
    use history\chat_history_update_meta;
    use history\cleanup_old_logs;
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
    use resolver\get_latest_activity_chat_sid;
    use resolver\get_latest_chat_sid;
    use resolver\is_archive_chat_segment;
    use resolver\get_bare_chat_sid;
    use view\render_chat_item;
    use view\render_chat_list;
    use view\render_archived_chat_list;
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


    // 建立對話標題（純函數——超過上限的內容截斷；唔加省略號——顯示層自理）
    public function build_chat_title($content)
    {
        $chat_title_max_chars = 200;
        $title_text = preg_replace('/\s+/u', ' ', trim($content));

        // 判定：未超過上限 → 原樣回傳
        if (mb_strlen($title_text) <= $chat_title_max_chars) {
            return $title_text;
        }

        return mb_substr($title_text, 0, $chat_title_max_chars);
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

        return "/" . $project_segment . "/" . $this->build_chat_segment($chat_sid, $chat_status) . "/";
    }

    // 歷史檔路徑（localdb/history/{project_slug}/{chat_sid}{尾綴}——命名規則唯一來源；尾綴由呼叫端帶．jsonl／_meta.json）
    public function get_chat_history_file_path($chat_id, $file_suffix)
    {
        $daemon_configs = hkai__core()->get_daemon_config();
        $project_slug   = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);
        $chat_row       = $this->get_chat_row($chat_id);

        $chat_sid = "";

        // 判定：對話列存在 → 取 sid
        if ($chat_row !== null) {
            $chat_sid = $chat_row["chat_sid"];
        }

        return $daemon_configs["daemon_history_dir"] . "/" . $project_slug . "/" . $chat_sid . $file_suffix;
    }


    // 目前選取的對話 sid：URL 第二段（chat_sid——URL 主鍵；歸檔語境形 archive@<chat_sid> → 回純 sid）
    // 對話資料以 DB 為 SSOT（chats 表——repo_select 為唯一讀取入口）；查無此對話 → 回 null（不取 default）
    public function get_selected_chat_sid()
    {
        $requestedChatSid = sc_get_friendly(1);

        // URL 未指定對話 sid（根 URL）→ 無選取對話
        if ($requestedChatSid === null) {
            return null;
        }

        // 查表用純 sid（歸檔語境形 archive@<chat_sid> → @ 後；其餘 → 原值）
        $lookup_chat_sid = $this->get_bare_chat_sid($requestedChatSid);

        $chat_row = $this->get_chat_row_by_sid($lookup_chat_sid);

        // chats 表查無此對話（sid 無效）→ 無選取對話
        if ($chat_row === null) {
            return null;
        }

        // 判定：歸檔語境（archive@<chat_sid>）→ 採用（瀏覽歸檔語境——歸檔對話喺此語境照顯示）
        if ($this->is_archive_chat_segment($requestedChatSid)) {
            return $lookup_chat_sid;
        }

        // 判定：已歸檔對話（顯示唔載——歸檔＝當未選中，右邊一律唔顯示）
        if ($chat_row["chat_status"] !== "normal") {
            return null;
        }

        return $lookup_chat_sid;
    }



    // 是否有選取專案（空狀態顯隱判定——兩處輸出共用）
    public function is_project_selected()
    {
        $selected_project_slug = sc_ai_projects()->project()->get_selected_project_slug();
        
        return (string)$selected_project_slug !== "";
    }


}
