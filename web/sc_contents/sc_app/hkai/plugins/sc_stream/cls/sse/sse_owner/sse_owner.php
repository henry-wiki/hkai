<?php

namespace sc\app\hkai\plugins\sc_stream\sse\sse_owner;

// == sse_owner_Class：狀態／草稿串流「連接身份」子域（singleton——經 sc_stream()->sse_owner() 取得） ==
// == 職責：每頁一格（view_id 資料夾）登記身份檔、收工刪檔、清掃殘留（唔做接管——EventSource 自動重連會互殺） ==
// == 上游：sse\sse_state\sse_state_Class、sse\sse_prompt\sse_prompt_Class（開線 open／收工 close）
// == 下游：sc_uuid7、sc_write_json、glob／unlink／rmdir

class sse_owner_Class
{
    // 殘留門檻（秒）——連線上限 300 秒再加緩衝；超過仍未收工即屬死檔
    private const STALE_SECONDS = 320;

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

    /**
     * 開線登記：寫入本頁身份檔（回身份檔路徑；冇頁身份＝唔登記，回空）
     *
     * @param string $view_id 本頁參照檔 id（32 hex；空＝唔登記）
     * @param string $channel 頻道名（state_stream／prompt_stream——同頁兩條線各有自己身份檔）
     * @return string
     */
    public function open($view_id, $channel)
    {
        // 判定：冇頁身份 → 唔登記（單一頁無需接管）
        if ($view_id === "") {
            return "";
        }

        $owner_dir = $this->get_owner_dir($view_id);

        // 判定：本頁格未存在 → 開（本頁第一條連線）
        if (is_dir($owner_dir) === false) {
            mkdir($owner_dir, 0777, true);
        }

        $owner_file = $owner_dir . "/" . $channel . "_" . sc_uuid7() . ".json";

        $owner_payload = [];
        $owner_payload["view_id"]    = $view_id;
        $owner_payload["channel"]    = $channel;
        $owner_payload["started_at"] = date("Y-m-d H:i:s");

        sc_write_json($owner_file, $owner_payload);

        $this->sweep_all();

        return $owner_file;
    }

    /**
     * 收工：刪自己身份檔；格內冇其它連線 → 連格一齊刪
     *
     * @param string $owner_file 本連接身份檔（open 回傳）
     * @return void
     */
    public function close($owner_file)
    {
        // 判定：冇身份檔（未登記）→ 唔做
        if ($owner_file === "") {
            return;
        }

        $owner_dir = dirname($owner_file);

        // 判定：身份檔仍在 → 刪
        if (is_file($owner_file) === true) {
            unlink($owner_file);
        }

        @rmdir($owner_dir);
    }

    /**
     * 全站殘留清掃（舊制平放檔＋各頁格死檔；清完空咗嘅格一齊刪）
     *
     * @return void
     */
    public function sweep_all()
    {
        $owner_root_dir = $this->get_owner_root_dir();

        // 舊制：身份檔平放喺根目錄（升級前留低嘅死檔）
        $this->sweep_stale_files($owner_root_dir);

        $owner_view_dirs = glob($owner_root_dir . "/*", GLOB_ONLYDIR);

        // 判定：冇任何頁格 → 完
        if (is_array($owner_view_dirs) === false) {
            return;
        }

        // 逐個頁格：清死檔，清完冇檔就刪格
        foreach ($owner_view_dirs as $owner_view_dir_item) {
            $this->sweep_stale_files($owner_view_dir_item);

            $remaining_files = glob($owner_view_dir_item . "/*.json");

            // 判定：格內已冇身份檔 → 刪格
            if (count($remaining_files) < 1) {
                @rmdir($owner_view_dir_item);
            }
        }
    }

    // 清一格內嘅殘留身份檔（只動一層）
    private function sweep_stale_files($owner_dir)
    {
        $owner_files = glob($owner_dir . "/*.json");

        // 判定：格內冇身份檔 → 完
        if (count($owner_files) < 1) {
            return;
        }

        // 逐個身份檔：夠鐘即刪
        foreach ($owner_files as $owner_file_item) {
            // 判定：未夠時限 → 仲可能活住，唔刪
            if (time() - filemtime($owner_file_item) <= self::STALE_SECONDS) {
                continue;
            }

            unlink($owner_file_item);
        }
    }

    // 身份檔根目錄
    private function get_owner_root_dir()
    {
        return sc_get_config("hkai.stream_dir") . "/sse_owner";
    }

    // 本頁格路徑（根目錄／view_id）
    private function get_owner_dir($view_id)
    {
        return $this->get_owner_root_dir() . "/" . $view_id;
    }
}
