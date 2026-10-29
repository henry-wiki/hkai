<?php

namespace sc\app\hkai\plugins\sc_ai_health\stream;

// == stream_Class：stream 域本體（stream.php——singleton；呼叫形 sc_ai_health()->stream()->…） ==
// == 職責：stream.json／viewers 讀寫＋比對（trait：init_stream_snapshot、create_viewer_snapshot） ==
// == 上游：request\health_stream、sc_app/hkai/index.php；下游：data/stream/ ==

class stream_Class
{
    use update_gate;
    use init_stream_snapshot;
    use create_viewer_snapshot;

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

    // 每個監視對象一個記錄：rev（內容變先 +1）、lastupdate（變動時間）、entries（內容）
    public function build_stream_targets($snapshot, $saved_targets)
    {
        $stream_targets = [];

        foreach ($snapshot as $monitor_name => $monitor_value) {
            $saved_target = $saved_targets[$monitor_name] ?? null;

            $stream_targets[$monitor_name] = sc_ai_health()->stream()->build_stream_target($monitor_value, $saved_target);
        }

        return $stream_targets;
    }

    // 單一對象記錄（內容與存檔相同 → 沿用 rev／lastupdate；不同 → rev+1）
    public function build_stream_target($monitor_value, $saved_target)
    {
        $entries_json = json_encode($monitor_value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $saved_entries      = ($saved_target === null) ? null : ($saved_target["entries"] ?? null);
        $saved_entries_json = ($saved_entries === null) ? null : json_encode($saved_entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stream_target = [];

        // 判定：內容與存檔相同 → 沿用版本
        if ($saved_entries_json === $entries_json) {
            $stream_target["rev"]        = (int)($saved_target["rev"] ?? 1);
            $stream_target["lastupdate"] = (string)($saved_target["lastupdate"] ?? date("Y-m-d H:i:s"));
        }
        // 判定：內容有變（或首次）→ rev+1
        else {
            $stream_target["rev"]        = (int)($saved_target["rev"] ?? 0) + 1;
            $stream_target["lastupdate"] = date("Y-m-d H:i:s");
        }

        $stream_target["entries"] = $monitor_value;

        return $stream_target;
    }

    // 比對兩份 targets（rev＋entries 全同 → true）
    public function is_targets_same($left_targets, $right_targets)
    {
        // 判定：項數不同 → 不同
        if (count($left_targets) !== count($right_targets)) {
            return false;
        }

        foreach ($right_targets as $target_name => $right_target) {
            $left_target = $left_targets[$target_name] ?? null;

            // 判定：左邊冇此項 → 不同
            if ($left_target === null) {
                return false;
            }

            // 判定：rev 不同 → 不同
            if ((int)$left_target["rev"] !== (int)$right_target["rev"]) {
                return false;
            }

            // 判定：左邊有 gate_at 而右邊唔同（缺／舊值）→ 不同（要重寫一次記低閘值）
            if (array_key_exists("gate_at", $left_target) === true) {
                $left_gate_at  = (int)($left_target["gate_at"] ?? -1);
                $right_gate_at = (int)($right_target["gate_at"] ?? -1);

                // 判定：閘值不同 → 不同
                if ($left_gate_at !== $right_gate_at) {
                    return false;
                }
            }
        }

        return true;
    }

    // 讀 stream.json 的 targets（真實版本；冇檔 → 空）
    public function read_stream_snapshot()
    {
        $stream_file = sc_get_config("hkai.stream_dir") . "/stream.json";

        // 判定：冇檔 → 空
        if (is_file($stream_file) === false) {
            return [];
        }

        $stream_text = @file_get_contents($stream_file);

        // 判定：讀唔到 → 空
        if ($stream_text === false) {
            return [];
        }

        $stream_data = json_decode($stream_text, true);

        // 判定：解析失敗 → 空
        if (is_array($stream_data) === false) {
            return [];
        }

        $stream_targets = $stream_data["targets"] ?? [];

        // 判定：targets 唔係陣列 → 空
        if (is_array($stream_targets) === false) {
            return [];
        }

        return $stream_targets;
    }

    // -------------------------------------------------------------------------------------------------------------------------
    // 由 stream.json 抽各對象 entries（{monitor_name: entries}）
    // 上游：get_snapshot_changes()（每 tick 讀完快照後）
    // 下游：build_stream_targets()（比對只需內容，唔要 rev／lastupdate）
    // -------------------------------------------------------------------------------------------------------------------------
    public function get_snapshot_entries($stream_snapshot)
    {
        $snapshot_entries = [];

        foreach ($stream_snapshot as $target_name => $stream_target) {
            $snapshot_entries[$target_name] = $stream_target["entries"] ?? [];
        }

        return $snapshot_entries;
    }

    // 寫 stream.json（原子寫）
    public function write_stream_snapshot($stream_targets)
    {
        $stream_dir  = sc_get_config("hkai.stream_dir");
        $stream_file = $stream_dir . "/stream.json";
        $temp_file   = $stream_file . ".tmp";

        // 判定：目錄唔存在 → 建立
        if (is_dir($stream_dir) === false) {
            @mkdir($stream_dir, 0777, true);
        }

        $created_at = date("Y-m-d H:i:s");

        // 判定：舊檔有 created_at → 沿用
        if (is_file($stream_file) === true) {
            $old_text = @file_get_contents($stream_file);
            $old_data = ($old_text === false) ? null : json_decode($old_text, true);

            // 判定：舊檔解析成功且有 created_at → 沿用
            if (is_array($old_data) === true) {
                $created_at = (string)($old_data["created_at"] ?? $created_at);
            }
        }

        $stream_data                 = [];
        $stream_data["created_at"]   = $created_at;
        $stream_data["updated_at"]   = date("Y-m-d H:i:s");
        $stream_data["targets"]      = (count($stream_targets) < 1) ? new \stdClass() : $stream_targets;

        $stream_json = json_encode($stream_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $written = @file_put_contents($temp_file, $stream_json);

        // 判定：寫入失敗 → 回 false
        if ($written === false) {
            return false;
        }

        @rename($temp_file, $stream_file);

        return true;
    }

    // 讀本頁 viewer 檔的 targets（冇 id／冇檔 → 空）
    public function read_viewer_snapshot($viewer_id)
    {
        // 判定：冇 viewer id → 空
        if ($viewer_id === "") {
            return [];
        }

        $viewer_file = sc_get_config("hkai.stream_dir") . "/viewers/" . $viewer_id . ".json";

        // 判定：冇檔 → 空
        if (is_file($viewer_file) === false) {
            return [];
        }

        $viewer_text = @file_get_contents($viewer_file);

        // 判定：讀唔到 → 空
        if ($viewer_text === false) {
            return [];
        }

        $viewer_data = json_decode($viewer_text, true);

        // 判定：解析失敗 → 空
        if (is_array($viewer_data) === false) {
            return [];
        }

        $viewer_targets = $viewer_data["targets"] ?? [];

        // 判定：targets 唔係陣列 → 空
        if (is_array($viewer_targets) === false) {
            return [];
        }

        return $viewer_targets;
    }

    // 更新本頁 viewer 檔時間（ping 保活——只改 updated_at，targets／created_at 原封；唔用 touch）
    public function refresh_viewer_timestamp($viewer_id)
    {
        // 判定：冇 viewer id → 唔做
        if ($viewer_id === "") {
            return false;
        }

        $viewer_dir  = sc_get_config("hkai.stream_dir") . "/viewers";
        $viewer_file = $viewer_dir . "/" . $viewer_id . ".json";

        // 判定：檔唔存在 → 唔做（新建由 create_viewer_snapshot 負責）
        if (is_file($viewer_file) === false) {
            return false;
        }

        $viewer_text = @file_get_contents($viewer_file);

        // 判定：讀唔到 → 唔做
        if ($viewer_text === false) {
            return false;
        }

        $viewer_data = json_decode($viewer_text, true);

        // 判定：內容壞 → 唔做
        if (is_array($viewer_data) === false) {
            return false;
        }

        $viewer_data["updated_at"] = date("Y-m-d H:i:s");

        $viewer_json = json_encode($viewer_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $temp_file   = $viewer_file . ".tmp";

        $written = @file_put_contents($temp_file, $viewer_json);

        // 判定：寫入失敗 → 回 false
        if ($written === false) {
            return false;
        }

        return @rename($temp_file, $viewer_file);
    }

    // 同步本頁 viewer 檔（targets＋updated_at；created_at 保留）
    public function sync_viewer_snapshot($viewer_id, $items)
    {
        // 判定：冇 viewer id → 唔寫
        if ($viewer_id === "") {
            return false;
        }

        $viewer_dir  = sc_get_config("hkai.stream_dir") . "/viewers";
        $viewer_file = $viewer_dir . "/" . $viewer_id . ".json";
        $temp_file   = $viewer_file . ".tmp";

        $created_at = date("Y-m-d H:i:s");

        // 判定：舊檔存在 → 嘗試沿用 created_at
        if (is_file($viewer_file) === true) {
            $old_text = @file_get_contents($viewer_file);
            $old_data = ($old_text === false) ? null : json_decode($old_text, true);

            // 判定：舊檔有 created_at → 沿用
            if (is_array($old_data) === true && isset($old_data["created_at"]) === true) {
                $created_at = (string)$old_data["created_at"];
            }
        }

        $viewer_data               = [];
        $viewer_data["created_at"] = $created_at;
        $viewer_data["updated_at"] = date("Y-m-d H:i:s");
        $viewer_data["targets"]      = (count($items) < 1) ? new \stdClass() : $items;

        $viewer_json = json_encode($viewer_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $written = @file_put_contents($temp_file, $viewer_json);

        // 判定：寫入失敗 → 回 false
        if ($written === false) {
            return false;
        }

        @rename($temp_file, $viewer_file);

        return true;
    }

    // 讀某對話的訊息真相（chats/{chat_sid}.json；冇檔 → null）
    public function read_chat_snapshot($chat_sid)
    {
        $chat_file = sc_get_config("hkai.stream_dir") . "/chats/" . $chat_sid . ".json";

        // 判定：冇檔 → null
        if (is_file($chat_file) === false) {
            return null;
        }

        $chat_text = @file_get_contents($chat_file);

        // 判定：讀唔到 → null
        if ($chat_text === false) {
            return null;
        }

        $chat_data = json_decode($chat_text, true);

        // 判定：解析失敗 → null
        if (is_array($chat_data) === false) {
            return null;
        }

        return $chat_data;
    }

    // 寫某對話的訊息真相（chats/{chat_sid}.json；內容變 → rev+1；原子寫）
    public function write_chat_snapshot($chat_sid, $entries, $saved_target)
    {
        $chat_dir  = sc_get_config("hkai.stream_dir") . "/chats";
        $chat_file = $chat_dir . "/" . $chat_sid . ".json";
        $temp_file = $chat_file . ".tmp";

        // 判定：目錄唔存在 → 建立
        if (is_dir($chat_dir) === false) {
            @mkdir($chat_dir, 0777, true);
        }

        $chat_target                 = sc_ai_health()->stream()->build_stream_target($entries, $saved_target);
        $chat_target["chat_sid"]     = $chat_sid;

        $chat_json = json_encode($chat_target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $written = @file_put_contents($temp_file, $chat_json);

        // 判定：寫入失敗 → false
        if ($written === false) {
            return null;
        }

        @rename($temp_file, $chat_file);

        return $chat_target;
    }

    // 寫某對話的訊息真相（已砌好的 target——零比對；原子寫）
    public function write_chat_snapshot_raw($chat_sid, $chat_target)
    {
        $chat_dir  = sc_get_config("hkai.stream_dir") . "/chats";
        $chat_file = $chat_dir . "/" . $chat_sid . ".json";
        $temp_file = $chat_file . ".tmp";

        // 判定：目錄唔存在 → 建立
        if (is_dir($chat_dir) === false) {
            @mkdir($chat_dir, 0777, true);
        }

        $chat_json = json_encode($chat_target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $written = @file_put_contents($temp_file, $chat_json);

        // 判定：寫入失敗 → false
        if ($written === false) {
            return false;
        }

        return @rename($temp_file, $chat_file);
    }

    // 讀某 viewer 已見的對話訊息版本（chats/viewers/{viewer_id}.json；冇檔 → null）
    public function read_chat_viewer_snapshot($viewer_id)
    {
        $viewer_file = sc_get_config("hkai.stream_dir") . "/chats/viewers/" . $viewer_id . ".json";

        // 判定：冇檔 → null
        if (is_file($viewer_file) === false) {
            return null;
        }

        $viewer_text = @file_get_contents($viewer_file);

        // 判定：讀唔到 → null
        if ($viewer_text === false) {
            return null;
        }

        $viewer_data = json_decode($viewer_text, true);

        // 判定：解析失敗 → null
        if (is_array($viewer_data) === false) {
            return null;
        }

        return $viewer_data;
    }

    // 同步某 viewer 已見的對話訊息版本（原子寫）
    public function sync_chat_viewer_snapshot($viewer_id, $chat_target)
    {
        $viewer_dir  = sc_get_config("hkai.stream_dir") . "/chats/viewers";
        $viewer_file = $viewer_dir . "/" . $viewer_id . ".json";
        $temp_file   = $viewer_file . ".tmp";

        // 判定：目錄唔存在 → 建立
        if (is_dir($viewer_dir) === false) {
            @mkdir($viewer_dir, 0777, true);
        }

        $viewer_json = json_encode($chat_target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $written = @file_put_contents($temp_file, $viewer_json);

        // 判定：寫入失敗 → false
        if ($written === false) {
            return false;
        }

        return @rename($temp_file, $viewer_file);
    }

    // -------------------------------------------------------------------------------------------------------------------------
    // 對話訊息比對：chats/{chat_sid}.json（真相）vs chats/viewers/{viewer_id}.json（本頁已見）
    // 上游：get_stream_events()（每 tick，只比當前對話）
    // 下游：read_chat_snapshot()、read_chat_viewer_snapshot()、build_stream_target()、message()->compare_entries()、sync_chat_viewer_snapshot()
    // 出口：① 未寫快照／未帶參數 → null　② 首次或換對話 → 寫初值、回 null　③ 版本相同 → null　④ 有變 → 同步＋回 event
    // -------------------------------------------------------------------------------------------------------------------------
    public function get_chat_snapshot_changes($viewer_id, $chat_sid)
    {
        // 判定：本頁冇對話／冇 viewer → 唔做
        if ($chat_sid === "" || $viewer_id === "") {
            return null;
        }

        // 步驟①：讀本對話真相（chats/{chat_sid}.json——由 init_stream_snapshot 每秒寫）
        $chat_snapshot = sc_ai_health()->stream()->read_chat_snapshot($chat_sid);

        // 判定：init 未寫（理論上唔會）→ 唔做
        if ($chat_snapshot === null) {
            return null;
        }

        // 步驟②：由真相檔取訊息 entries
        $current_message_entries = $chat_snapshot["entries"];

        // 步驟③：讀本頁已見的對話版本（chats/viewers/{viewer_id}.json）
        $viewer_chat_snapshot = sc_ai_health()->stream()->read_chat_viewer_snapshot($viewer_id);

        // 步驟④：以本頁版本為基準砌當刻 target（內容同 → 沿用 rev；唔同 → rev+1）
        $current_chat_target = sc_ai_health()->stream()->build_stream_target($current_message_entries, $viewer_chat_snapshot);

        // 判定：寫檔失敗 → 唔推
        if ($current_chat_target === null) {
            return null;
        }

        // 步驟⑤：標明此版本屬於邊個對話（換對話時判斷用）
        $current_chat_target["chat_sid"] = $chat_sid;

        // 判定：本頁未有此對話版本（首次／換對話）→ 寫初值，唔推
        if ($viewer_chat_snapshot === null) {
            sc_ai_health()->stream()->sync_chat_viewer_snapshot($viewer_id, $current_chat_target);

            return null;
        }

        // 判定：本頁版本屬於另一個對話 → 換成新對話初值，唔推
        if ((string)($viewer_chat_snapshot["chat_sid"] ?? "") !== $chat_sid) {
            sc_ai_health()->stream()->sync_chat_viewer_snapshot($viewer_id, $current_chat_target);

            return null;
        }

        // 判定：版本相同 → 冇變
        if ((int)($viewer_chat_snapshot["rev"] ?? 0) === (int)$current_chat_target["rev"]) {
            return null;
        }

        $previous_entries = $viewer_chat_snapshot["entries"] ?? null;

        $entry_changes = sc_ai_health()->monitor()->message()->compare_entries($previous_entries, $current_message_entries);

        $change_detail = [];
        $change_detail["rev"]        = (int)$current_chat_target["rev"];
        $change_detail["lastupdate"] = (string)$current_chat_target["lastupdate"];

        // 判定：可逐條比對 → 只帶 changes
        if ($entry_changes !== null) {
            $change_detail["changes"] = $entry_changes;
        }
        // 判定：不可逐條比對 → 帶整份前後
        else {
            $change_detail["previous"] = $previous_entries;
            $change_detail["current"]  = $current_message_entries;
        }

        $change_event            = [];
        $change_event["changed"] = ["message"];
        $change_event["message"] = $change_detail;
        $change_event["viewer_id"] = $viewer_id;

        sc_ai_health()->stream()->sync_chat_viewer_snapshot($viewer_id, $current_chat_target);

        return $change_event;
    }

    // -------------------------------------------------------------------------------------------------------------------------
    // 本頁變更總入口：頁面清單 ＋ 對話訊息（比對統一在 stream 域）
    // 上游：request/health_stream.php 主迴圈（每 tick）
    // 下游：get_snapshot_changes()、get_chat_snapshot_changes()
    // 回傳：event 陣列（0–2 個）——由呼叫端負責推送
    // -------------------------------------------------------------------------------------------------------------------------
    public function get_stream_events($viewer_id, $project_slug, $chat_sid)
    {
        $stream_events = [];

        $page_event = sc_ai_health()->stream()->get_snapshot_changes($viewer_id, $project_slug, $chat_sid);

        // 判定：頁面清單有變 → 記入
        if ($page_event !== null) {
            $stream_events[] = $page_event;
        }

        $message_event = sc_ai_health()->stream()->get_chat_snapshot_changes($viewer_id, $chat_sid);

        // 判定：對話訊息有變 → 記入
        if ($message_event !== null) {
            $stream_events[] = $message_event;
        }

        return $stream_events;
    }

    // -------------------------------------------------------------------------------------------------------------------------
    // 頁面清單比對：寫快照 → 讀本頁版本 → 比對 prompt／chat／project
    // 上游：get_stream_events()（每 tick）
    // 下游：init_stream_snapshot()、read_viewer_snapshot()、build_stream_targets()、diff_viewer_targets()、sync_viewer_snapshot()
    // 出口：① 無基線 → 同步初值、回 null　② 無變化 → 回 null　③ 有變化 → 同步本頁版本、回 event
    // -------------------------------------------------------------------------------------------------------------------------
    public function get_snapshot_changes($viewer_id, $project_slug, $chat_sid)
    {
        // 步驟①：寫當刻快照（stream.json＋chats/{chat_sid}.json），回傳同一份 targets
        $stream_snapshot = sc_ai_health()->stream()->init_stream_snapshot($project_slug, $chat_sid);

        // 步驟②：由快照抽 entries（{monitor_name: entries}——比對只需內容）
        $monitor_entries = sc_ai_health()->stream()->get_snapshot_entries($stream_snapshot);

        // 步驟③：讀本頁已見版本（viewers/{viewer_id}.json）
        $viewer_snapshot = sc_ai_health()->stream()->read_viewer_snapshot($viewer_id);

        // 步驟④：以本頁版本為基準砌當刻 targets（內容同 → 沿用 rev；唔同 → rev+1）
        $current_targets = sc_ai_health()->stream()->build_stream_targets($monitor_entries, $viewer_snapshot);

        // 步驟⑤：判斷本頁有冇基線（空＝新建／被 sweep）
        $has_viewer_baseline = (count($viewer_snapshot) > 0);

        // 出口①：本頁未有基線（新建／被 sweep／空）→ 同步初值，之後照推一次 conn（首次激活）
        if ($has_viewer_baseline === false) {
            sc_ai_health()->stream()->sync_viewer_snapshot($viewer_id, $current_targets);

            // 判定：快照冇 conn → 冇嘢可推
            if (isset($current_targets["conn"]) === false) {
                return null;
            }

            $conn_targets = ["conn" => $current_targets["conn"]];

            return sc_ai_health()->stream()->diff_viewer_targets($conn_targets, []);
        }

        $change_event = sc_ai_health()->stream()->diff_viewer_targets($current_targets, $viewer_snapshot);

        // 出口②：冇變化 → 唔同步、唔推（省一次寫盤）
        if ($change_event === null) {
            return null;
        }

        // 出口③：有變化 → 同步本頁版本，回 changes
        sc_ai_health()->stream()->sync_viewer_snapshot($viewer_id, $current_targets);

        return $change_event;
    }

    // diff：當刻版本 vs 本頁版本 → 只回有變的項（無變 → null）
    public function diff_viewer_targets($current_targets, $viewer_snapshot)
    {
        $changed_targets = [];

        foreach ($current_targets as $target_name => $current_target) {
            $viewer_snapshot_item = $viewer_snapshot[$target_name] ?? null;

            $viewer_rev = ($viewer_snapshot_item === null) ? 0 : (int)$viewer_snapshot_item["rev"];

            // 判定：本頁未有此項或 rev 不同 → 記入 changed
            if ($viewer_rev !== (int)$current_target["rev"]) {
                $changed_targets[] = $target_name;
            }
        }

        // 判定：冇變化 → null（唔推）
        if (count($changed_targets) < 1) {
            return null;
        }

        $change_event            = [];
        $change_event["changed"] = $changed_targets;

        // 逐項帶「邊幾條變」（只列有變嘅條目）
        foreach ($changed_targets as $changed_target) {
            $current_target  = $current_targets[$changed_target];
            $viewer_snapshot_item = $viewer_snapshot[$changed_target] ?? null;

            $previous_entries = ($viewer_snapshot_item === null) ? null : ($viewer_snapshot_item["entries"] ?? null);
            $current_entries  = $current_target["entries"];

            $change_detail = [];
            $change_detail["rev"]        = (int)$current_target["rev"];
            $change_detail["lastupdate"] = (string)$current_target["lastupdate"];

            $entry_changes = sc_ai_health()->monitor()->compare_target_entries($changed_target, $previous_entries, $current_entries);

            // 判定：可逐條比對 → 只帶 changes
            if ($entry_changes !== null) {
                $change_detail["changes"] = $entry_changes;
            }
            // 判定：不可逐條比對 → 帶整份前後
            else {
                $change_detail["previous"] = $previous_entries;
                $change_detail["current"]  = $current_entries;
            }

            $change_event[$changed_target] = $change_detail;
        }

        return $change_event;
    }
}
