<?php

namespace sc\app\hkai\plugins\sc_stream;

// == sc_stream_Class：stream 域根（singleton——經 sc_stream() 取得） ==
// == 子域：sse()／sse_state()／sse_prompt()（輸出＋迴圈）；snapshot()（快照＋頁面比對）；monitor()（監視對象） ==
// == 職責：子域 accessor（SSE 主迴圈直接叫 sse_state()->get_snapshot_changes_for_state()） ==
// == 上游：request\state_stream、request\prompt_stream；下游：snapshot\*、monitor\* ==

class sc_stream_Class
{
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
    {}

    /**
     * SSE 共用輸出（headers／flush／handshake／query 解析）
     *
     * @return \sc\app\hkai\plugins\sc_stream\sse\sse_Class
     */
    public function sse()
    {
        return sse\sse_Class::getInstance();
    }

    /**
     * 狀態串流主迴圈（每秒 diff）
     *
     * @return \sc\app\hkai\plugins\sc_stream\sse\sse_state\sse_state_Class
     */
    public function sse_state()
    {
        return sse\sse_state\sse_state_Class::getInstance();
    }

    /**
     * 草稿串流主迴圈（每 0.1 秒只比 prompt）
     *
     * @return \sc\app\hkai\plugins\sc_stream\sse\sse_prompt\sse_prompt_Class
     */
    public function sse_prompt()
    {
        return sse\sse_prompt\sse_prompt_Class::getInstance();
    }

    /**
     * 連接身份子域（每頁一格：登記／接管判斷／清掃）
     *
     * @return \sc\app\hkai\plugins\sc_stream\sse\sse_owner\sse_owner_Class
     */
    public function sse_owner()
    {
        return sse\sse_owner\sse_owner_Class::getInstance();
    }

    /**
     * 快照子域（view 檔／view id／diff／清理；state／prompt 兩線各自持有目標）
     *
     * @return \sc\app\hkai\plugins\sc_stream\snapshot\snapshot_Class
     */
    public function snapshot()
    {
        return snapshot\snapshot_Class::getInstance();
    }

    /**
     * 監視對象容器（chat／project／polling／git／prompt／message）
     *
     * @return \sc\app\hkai\plugins\sc_stream\monitor\monitor_Class
     */
    public function monitor()
    {
        return monitor\monitor_Class::getInstance();
    }

    // 解析 project_slug（頁面入口冇帶 → 由請求路徑取；request 端點只信傳入值）
    public function resolve_project_slug($request_project_slug = "")
    {
        $request_path        = sc_request()->path();
        $path_segments       = explode("/", trim($request_path, "/"));
        $is_request_endpoint = (strpos($request_path, "/request/") === 0);

        $raw_project_slug = $request_project_slug;

        // 判定：頁面入口且冇帶 → 由請求路徑取
        if ($raw_project_slug === "" && $is_request_endpoint === false) {
            $raw_project_slug = $path_segments[0] ?? "";
        }

        // 判定：request 端點且冇帶 → 由查詢參數取
        if ($raw_project_slug === "" && $is_request_endpoint === true) {
            $raw_project_slug = (string)($_GET["project_slug"] ?? "");
        }

        // 判定：冇專案段（首頁／request 端點）→ 空（唔 fallback 預設專案）
        if ($raw_project_slug === "") {
            return "";
        }

        $resolved_project = sc_ai_projects()->project()->resolve_project_slug($raw_project_slug);

        return $resolved_project["project_slug"];
    }

    // 解析 chat_sid（頁面入口冇帶 → 由請求路徑取；request 端點只信傳入值）
    public function resolve_chat_sid($request_chat_sid = "")
    {
        $request_path        = sc_request()->path();
        $path_segments       = explode("/", trim($request_path, "/"));
        $is_request_endpoint = (strpos($request_path, "/request/") === 0);

        $raw_chat_sid = $request_chat_sid;

        // 判定：頁面入口且冇帶 → 由請求路徑取
        if ($raw_chat_sid === "" && $is_request_endpoint === false) {
            $raw_chat_sid = $path_segments[1] ?? "";
        }

        // 判定：request 端點且冇帶 → 由查詢參數取
        if ($raw_chat_sid === "" && $is_request_endpoint === true) {
            $raw_chat_sid = (string)($_GET["chat_sid"] ?? "");
        }

        $resolved_chat = sc_ai_chats()->chat()->resolve_chat_sid($raw_chat_sid);

        return $resolved_chat["chat_sid"];
    }

    // 讀 view_id（本頁參照檔 id——只喺 request 端點 query 出現；頁面 meta 由 builder 直接輸出，冇路徑來源）
    public function get_view_id()
    {
        $query_data  = sc_request()->query();
        $raw_view_id = isset($query_data["view_id"]) ? (string)$query_data["view_id"] : "";

        // 判定：格式唔合法（只收 32 hex；空值同樣唔合法）→ 當冇（防止亂寫 view 檔）
        if (preg_match("/^[0-9a-f]{32}$/", $raw_view_id) !== 1) {
            return "";
        }

        return $raw_view_id;
    }
}
