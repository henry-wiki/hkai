<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll;

// == sc_ai_chats poll\poll_Class：poll 域本體（bridge 檔——singleton；bridging sc_ai_chats()->poll()） ==
// == 動作（cls/poll/ 各 trait——一檔一動作）＋基本功能住本體：get_poll_dir／get_archived_poll_dir／get_poll_file（路徑層——目錄制 messages/polling/{project_slug}/{message_id}；高度重用：file 14 用家、dir 6 用家） ==
// == 2026-09-27 六個 poll class 合成單一本體；本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名） ==

class poll_Class
{
    use action\delete_msg_files;
    use action\finalize_poll_jobs;
    use action\finalize_stale_job;
    use action\finalize_abnormal_jobs;
    use action\delete_poll_dir;
    use action\sweep_old_archived_messages;
    use status\append_msg_status;

    private static $instance = null;

    // msg 檔型別 → 副檔名（與 python _polling_msg_file_type 對稱——系統唯一契約）
    private $polling_msg_file_type = [
        "conf"                => "json",
        "status"              => "txt",
        "prompt"              => "json",
        "response"            => "txt",
        "response_thinking"   => "txt",
        "response_result"     => "txt",
        "log"                 => "txt",
        "stderr"              => "txt",
        "claim"               => "json",
        "permission"          => "json",
        "permission_decision" => "json",
        "error"               => "json",
        "stream_log"          => "jsonl",
        "error_log"           => "jsonl",
        "poll_list"           => "jsonl",
        "summarize"           => "txt",
        "summarize_result"    => "txt",
        "summarize_thinking"  => "txt",
    ];

    // 孤兒 job 停滯門檻（秒；plugin __init__.php 注入——class 只收值）
    private $stale_job_seconds = 0;

    // chat_id → project_slug 快取（同一請求內重複解析＝零額外查詢；掃描逐個目錄時必須）
    private $chat_project_slug_cache = [];

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

    // 注入 poll 設定（plugin __init__.php——集中於入口提供；class 只收值）
    public function initial_poll($poll_setup)
    {
        $this->stale_job_seconds = intval($poll_setup["stale_job_seconds"]);
    }

    /**
     * log 子域（獨立物件——cls/poll/log/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\poll\log\log_Class
     */
    public function log()
    {
        return log\log_Class::getInstance();
    }

    /**
     * request 子域（獨立物件——cls/poll/request/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\poll\request\request_Class
     */
    public function request()
    {
        return request\request_Class::getInstance();
    }

    /**
     * file 子域（獨立物件——cls/poll/file/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\poll\file\file_Class
     */
    public function file()
    {
        return file\file_Class::getInstance();
    }

    // msg_log 檔路徑（過度細微——住 poll 根；log 家族經 sc_ai_chats()->poll() 叫）
    public function get_msg_log_file($chat_id, $message_id)
    {
        return $this->get_poll_file($chat_id, $message_id, "log");
    }

    // ---- 基本功能（高度重用——路徑層；住本體，不另開檔） ----

    // 專案 slug（依 chat_id 解析——目錄命名用；查唔到回空字串；查過即快取）
    public function get_project_slug_by_chat($chat_id)
    {
        // 判定：同一請求內已解析過 → 回快取值
        if (isset($this->chat_project_slug_cache[(int)$chat_id])) {
            return $this->chat_project_slug_cache[(int)$chat_id];
        }

        $project_slug = "";
        $chat_row     = sc_ai_chats()->chat()->get_chat_row($chat_id);

        // 判定：對話列存在 → 由 project_id 取專案列（project 域預載表——零額外 SQL）
        if ($chat_row !== null) {
            $project_row = sc_ai_projects()->project()->get_project_row($chat_row["project_id"]);

            // 判定：專案列存在 → 取 slug
            if ($project_row !== null) {
                $project_slug = $project_row["project_slug"];
            }
        }

        $this->chat_project_slug_cache[(int)$chat_id] = $project_slug;

        return $project_slug;
    }

    // 訊息目錄名（{project_slug}/{message_id}——命名規則唯一來源；現行 polling/ 與歸檔 archive/ 同名）
    private function get_poll_dir_name($project_slug, $message_id)
    {
        return $project_slug . "/" . (int)$message_id;
    }

    // 訊息目錄路徑（messages/polling/{project_slug}/{message_id}——單一組裝來源）
    public function get_poll_dir($chat_id, $message_id)
    {
        $project_slug = $this->get_project_slug_by_chat($chat_id);

        return sc_ai_chats()->storage()->get_messages_dir() . "/" . $this->get_poll_dir_name($project_slug, $message_id);
    }

    // 歸檔訊息目錄路徑（archive/{project_slug}/{message_id}——歸檔側位置；同一個目錄名）
    public function get_archived_poll_dir($chat_id, $message_id)
    {
        $project_slug   = $this->get_project_slug_by_chat($chat_id);
        $daemon_configs = hkai__core()->get_daemon_config();

        return $daemon_configs["daemon_archive_dir"] . "/" . $this->get_poll_dir_name($project_slug, $message_id);
    }

    // 單一 type 路徑（目錄制：messages/polling/{project_slug}/{message_id}/msg_{type}.{ext}——純組裝，不依賴檔案存在）
    public function get_poll_file($chat_id, $message_id, $type)
    {
        $project_slug = $this->get_project_slug_by_chat($chat_id);

        return $this->get_poll_file_by_slug($project_slug, $message_id, $type);
    }

    // 單一 type 路徑（目錄名已知：{project_slug}/{message_id}——掃描用；免查 chat 列）
    public function get_poll_file_by_slug($project_slug, $message_id, $type)
    {
        $message_dir = sc_ai_chats()->storage()->get_messages_dir() . "/" . $this->get_poll_dir_name($project_slug, $message_id);
        
        $file_extension = "txt";
        if (isset($this->polling_msg_file_type[$type])) {
            $file_extension = $this->polling_msg_file_type[$type];
        }

        $matched_file = $message_dir . "/msg_{$type}.{$file_extension}";
        
        return $matched_file;
    }



}
