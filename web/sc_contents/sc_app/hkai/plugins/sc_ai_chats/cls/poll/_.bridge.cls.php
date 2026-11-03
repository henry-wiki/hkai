<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll;

// == sc_ai_chats poll\poll_Class：poll 域本體（bridge 檔——singleton；bridging sc_ai_chats()->poll()） ==
// == 動作（cls/poll/ 各 trait——一檔一動作）＋基本功能住本體：get_poll_dir／get_poll_dir_by_slug／get_poll_file（路徑層——目錄名唯一來源 get_poll_dir_name；歸檔側路徑交 archive 域） ==
// == 2026-09-27 六個 poll class 合成單一本體；本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名） ==

class poll_Class
{
    use action\delete_poll_files;
    use action\finalize_poll_jobs;
    use action\finalize_poll_jobs_by_identity;
    use action\finalize_stale_job;
    use action\finalize_abnormal_jobs;
    use action\reset_partial_response;
    use action\revive_dead_worker_messages;


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
        return sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "log");
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

    // 訊息目錄名（新：{project_slug}/{chat_sid}/{prompt_id}；舊訊息 fallback {project_slug}/{message_id}）
    public function get_poll_dir_name($project_slug, $message_id)
    {
        $message_dir_info = sc_ai_chats()->poll()->_get_message_dir_info($message_id);

        // 判定：有 chat_sid＋prompt_id → 新結構
        if ($message_dir_info !== null) {
            $chat_sid  = (string)$message_dir_info["chat_sid"];
            $prompt_id = (string)$message_dir_info["prompt_id"];

            // 判定：兩者都有值 → 三層
            if ($chat_sid !== "" && $prompt_id !== "") {
                return $project_slug . "/" . $chat_sid . "/" . $prompt_id;
            }
        }

        return $project_slug . "/" . (int)$message_id;
    }

    // 訊息目錄身份（DB：prompt_id＋chat_sid＋chat_id——冇此訊息＝null）
    public function _get_message_dir_info($message_id)
    {
        $connection = sc_db()->connection("sqlite");

        $rows = $connection->select(
            "SELECT m.prompt_id, m.chat_id, c.chat_sid FROM messages m LEFT JOIN chats c ON c.chat_id = m.chat_id WHERE m.message_id = ?",
            [(int)$message_id]
        );

        // 判定：查唔到 → null
        if (count($rows) < 1) {
            return null;
        }

        return $rows[0];
    }

    // 訊息現存目錄（polling 先、archive 後——單一路徑來源：get_poll_dir_name；搵唔到＝空字串）
    public function get_existing_message_dir($message_id)
    {
        $message_dir_info = $this->_get_message_dir_info($message_id);

        // 判定：DB 冇此訊息 → 空
        if ($message_dir_info === null) {
            return "";
        }

        $project_slug = $this->get_project_slug_by_chat($message_dir_info["chat_id"]);

        // 判定：專案 slug 空（專案已刪）→ 空
        if ($project_slug === "") {
            return "";
        }

        $polling_dir = $this->get_poll_dir_by_slug($project_slug, $message_id);

        // 判定：polling 箱存在 → 回
        if (is_dir($polling_dir)) {
            return $polling_dir;
        }

        $archived_dir = sc_ai_chats()->archive()->get_archived_dir_by_slug($project_slug, $message_id);

        // 判定：archive 箱存在 → 回
        if (is_dir($archived_dir)) {
            return $archived_dir;
        }

        return "";
    }

    // 訊息現存檔（polling 先、archive 後——檔名契約同 get_poll_file；搵唔到＝空字串）
    public function get_existing_poll_file($message_id, $type)
    {
        $message_dir = $this->get_existing_message_dir($message_id);

        // 判定：箱唔存在 → 空
        if ($message_dir === "") {
            return "";
        }

        // 判定：conf 一律叫 meta.json
        if ($type === "conf") {
            return $message_dir . "/meta.json";
        }

        $file_extension = $this->polling_msg_file_type[$type] ?? "txt";

        return $message_dir . "/msg_" . $type . "." . $file_extension;
    }

    // 對話輪詢目錄（messages/polling/{project_slug}/{chat_sid}——新三層父層；純組裝）
    public function get_poll_chat_dir($project_slug, $chat_sid)
    {
        $messages_polling_dir = sc_get_config("hkai.messages_polling_dir");

        return $messages_polling_dir . "/" . $project_slug . "/" . $chat_sid;
    }

    // 訊息目錄路徑（polling/<project_slug>/<chat_sid>/<prompt_id>——chat_id 版；交 slug 版組裝）
    public function get_poll_dir($chat_id, $message_id)
    {
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        return $this->get_poll_dir_by_slug($project_slug, $message_id);
    }

    // 訊息目錄路徑（slug 版——目錄名唯一來源：get_poll_dir_name）
    public function get_poll_dir_by_slug($project_slug, $message_id)
    {
        return sc_get_config("hkai.messages_polling_dir") . "/" . $this->get_poll_dir_name($project_slug, $message_id);
    }

    // 單一 type 路徑（目錄制：messages/polling/{project_slug}/{message_id}/msg_{type}.{ext}——純組裝，不依賴檔案存在）
    public function get_poll_file($chat_id, $message_id, $type)
    {
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        return sc_ai_chats()->poll()->get_poll_file_by_slug($project_slug, $message_id, $type);
    }

    // 三層目錄（身份直組——零 DB 查詢：polling/<slug>/<chat_sid>/<prompt_id>）
    public function get_poll_dir_by_identity($project_slug, $chat_sid, $prompt_id)
    {
        return sc_get_config("hkai.messages_polling_dir") . "/" . $project_slug . "/" . $chat_sid . "/" . $prompt_id;
    }

    // 單一 type 路徑（目錄名已知：{project_slug}/{message_id}——掃描用；免查 chat 列）
    public function get_poll_file_by_slug($project_slug, $message_id, $type)
    {
        $message_dir = sc_ai_chats()->poll()->get_poll_dir_by_slug($project_slug, $message_id);
        
        $file_extension = "txt";
        if (isset($this->polling_msg_file_type[$type])) {
            $file_extension = $this->polling_msg_file_type[$type];
        }

        // 契約：conf 一律叫 meta.json（其餘 msg_{type}.{ext}）
        if ($type === "conf") {
            $matched_file = $message_dir . "/meta.json";
        } else {
            $matched_file = $message_dir . "/msg_{$type}.{$file_extension}";
        }
        
        return $matched_file;
    }



}
