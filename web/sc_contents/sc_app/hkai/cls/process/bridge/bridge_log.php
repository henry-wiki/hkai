<?php

namespace sc\app\hkai\cls\process\bridge;

// == bridge_log_Class：bridge audit log 讀取（singleton——經 hkai()->process()->bridge_log() 取得） ==
// == 職責：read_server_log_after_offset（offset 標記——0＝全量 tail、有效＝只讀新增）；私有 full／append 兩種讀法
// == 上游：sc_ai_settings server 分頁（開 modal／輪詢日誌）

class bridge_log_Class
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

    // 讀 server 日誌（offset 標記——0／無效＝全量 tail；有效＝只讀之後嘅新增）
    // 回 ["mode" => "full"|"append", "lines" => [行…]（時序舊→新）, "offset" => 新標記（最後完整行尾之位元組位置）]
    public function read_server_log_after_offset($log_byte_offset = 0, $log_line_limit = 500)
    {
        $python_script_dir = sc_get_config("services");
        $audit_log_file    = dirname($python_script_dir) . "/data/logs/bridge.audit.log";

        $read_result = [];
        $read_result["mode"]   = "full";
        $read_result["lines"]  = [];
        $read_result["offset"] = 0;

        // 判定：日誌檔唔存在 → 空結果
        if (!is_file($audit_log_file)) {
            return $read_result;
        }

        $file_size = filesize($audit_log_file);

        // 判定：帶咗 offset（> 0）→ 核對未輪替
        if ($log_byte_offset > 0) {
            // 判定：標記位仍喺檔內（未輪替）→ 增量讀
            if ($log_byte_offset <= $file_size) {
                return $this->read_server_log_append($audit_log_file, $log_byte_offset, $log_line_limit);
            }
        }

        return $this->read_server_log_full($audit_log_file, $file_size, $log_line_limit);
    }

    // 全量讀（檔尾窗口——最後 $log_line_limit 行完整行；offset＝最後完整行尾）
    private function read_server_log_full($audit_log_file, $file_size, $log_line_limit)
    {
        $read_result = [];
        $read_result["mode"]   = "full";
        $read_result["lines"]  = [];
        $read_result["offset"] = 0;

        // 讀取窗口（檔尾最多 256KB；細檔＝由頭讀）
        $read_byte_limit  = 256 * 1024;
        $read_byte_offset = $file_size - $read_byte_limit;

        // 判定：窗口起點為負（細檔）→ 由頭讀
        if ($read_byte_offset < 0) {
            $read_byte_offset = 0;
        }

        $file_handle = fopen($audit_log_file, "rb");

        // 判定：開唔到（檔案鎖／權限）→ 空結果
        if ($file_handle === false) {
            return $read_result;
        }

        fseek($file_handle, $read_byte_offset);
        $raw_chunk = fread($file_handle, $read_byte_limit);
        fclose($file_handle);

        // 判定：讀唔到 → 空結果
        if ($raw_chunk === false) {
            return $read_result;
        }

        // 標記（檔尾係完整行 → offset＝檔尾；尾行寫緊 → 停喺最後換行之後）
        $last_newline_position = strrpos($raw_chunk, "\n");

        // 判定：冇任何換行（極短檔）→ offset 留 0（下輪全量再嚟）
        if ($last_newline_position === false) {
            return $read_result;
        }

        // 判定：最後換行＝埋尾（完整收尾）→ offset＝檔尾
        if ($last_newline_position === strlen($raw_chunk) - 1) {
            $read_result["offset"] = $file_size;
        } else {
            // 判定：尾行寫緊 → offset＝最後換行之後
            $read_result["offset"] = $file_size - (strlen($raw_chunk) - $last_newline_position - 1);
        }

        // 切行（CRLF／LF／CR 混用都食）
        $log_lines = preg_split("/\r\n|\n|\r/", $raw_chunk);

        // 判定：窗口由檔中間開始（首行係半截）→ 丟首行
        if ($read_byte_offset > 0) {
            array_shift($log_lines);
        }

        // 去尾端空元素（檔尾換行切出——免空行收尾）
        while (end($log_lines) === "") {
            array_pop($log_lines);
        }

        // 判定：尾行未完整（寫緊）→ 抽走尾行（下輪完整先交——免半截又重複）
        if ($read_result["offset"] < $file_size) {
            array_pop($log_lines);
        }

        // 取末段（最後 $log_line_limit 行）
        $log_lines = array_slice($log_lines, -$log_line_limit);

        $read_result["lines"] = $log_lines;

        return $read_result;
    }

    // 增量讀（由 offset 讀到檔尾——只回完整行；offset 推進到最後完整行尾）
    private function read_server_log_append($audit_log_file, $log_byte_offset, $log_line_limit)
    {
        $read_result = [];
        $read_result["mode"]   = "append";
        $read_result["lines"]  = [];
        $read_result["offset"] = $log_byte_offset;

        $file_handle = fopen($audit_log_file, "rb");

        // 判定：開唔到 → 原樣回（下輪再試）
        if ($file_handle === false) {
            return $read_result;
        }

        fseek($file_handle, $log_byte_offset);
        $raw_chunk = fread($file_handle, 1024 * 1024);
        fclose($file_handle);

        // 判定：讀唔到 → 原樣回
        if ($raw_chunk === false) {
            return $read_result;
        }

        // 判定：無新內容 → 原樣回
        if ($raw_chunk === "") {
            return $read_result;
        }

        // 只計完整行（單輪上限 1MB——積壓分輪追；尾段未完嘅一行留下輪）
        $last_newline_position = strrpos($raw_chunk, "\n");

        // 判定：成段都係未完一行 → 原樣回
        if ($last_newline_position === false) {
            return $read_result;
        }

        $complete_chunk = substr($raw_chunk, 0, $last_newline_position + 1);

        // 切行（CRLF／LF／CR 混用都食）
        $log_lines = preg_split("/\r\n|\n|\r/", $complete_chunk);

        // 去尾端空元素（尾換行切出）
        while (end($log_lines) === "") {
            array_pop($log_lines);
        }

        // 判定：行數超上限（極端積壓）→ 只留最新 $log_line_limit 行
        if (count($log_lines) > $log_line_limit) {
            $log_lines = array_slice($log_lines, -$log_line_limit);
        }

        $read_result["lines"]  = $log_lines;
        $read_result["offset"] = $log_byte_offset + strlen($complete_chunk);

        return $read_result;
    }
}
