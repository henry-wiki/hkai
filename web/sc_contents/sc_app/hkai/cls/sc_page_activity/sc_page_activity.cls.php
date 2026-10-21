<?php

namespace sc\app\hkai\cls\sc_page_activity;

// == sc_page_activity_Class：hkai app 層頁面活動（singleton——經 bridging hkai__sc_page_activity() 取得） ==
// == 職責：頁面記憶——set_activity（寫檔）／get_activity（讀檔） ==
// == 儲存：檔案式（sc_storage/hkai/activity/{activity_name}.json——唔佔 SQLite）

class sc_page_activity_Class
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

    // 活動目錄（sc_storage/hkai/activity——未有就建立）
    private function get_activity_dir()
    {
        $activity_dir = hkai__core()->get_storage_dir() . "/activity";

        // 判定：目錄未存在 → 建立（遞迴）
        if (!is_dir($activity_dir)) {
            mkdir($activity_dir, 0777, true);
        }

        return $activity_dir;
    }

    // 活動檔路徑（{activity_name}.json——一個活動一檔）
    private function get_activity_file($activity_name)
    {
        $activity_dir = $this->get_activity_dir();

        return $activity_dir . "/" . $activity_name . ".json";
    }

    // 讀取活動檔全部記錄（檔案未存在／內容壞 → 空陣列；全域活動＝單 object → 包成單筆陣列）
    private function read_activity_records($activity_file)
    {
        // 判定：檔案未存在 → 無記錄
        if (!is_file($activity_file)) {
            return [];
        }

        $activity_raw     = file_get_contents($activity_file);
        $activity_records = json_decode($activity_raw, true);

        // 判定：解出唔係陣列 → 當無記錄
        if (!is_array($activity_records)) {
            return [];
        }

        // 判定：單 object（有 activity_content 鍵）→ 包成單筆陣列（全域活動格式）
        if (isset($activity_records["activity_content"])) {
            return [$activity_records];
        }

        return $activity_records;
    }

    // 寫入活動檔（全域＝單 object、有 scope＝array——一次過編碼落檔，LOCK_EX 防並行寫壞）
    private function write_activity_records($activity_file, $activity_records, $is_global_activity)
    {
        // 判定：全域活動 → 寫 object（唔用 array 包）
        if ($is_global_activity === true) {
            $activity_records = $activity_records[0];
        }

        $activity_json = json_encode($activity_records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        file_put_contents($activity_file, $activity_json, LOCK_EX);
    }

    // 判定記錄是否符合 scope（scope 有帶 project_id／chat_id 才比對——同 SQL 動態 WHERE 一致）
    private function is_activity_scope_match($activity_record, $activity_scope)
    {
        // 判定：帶 project_id → 必須相同
        if (isset($activity_scope["project_id"])) {
            $scope_project_id = (int)$activity_scope["project_id"];

            // 判定：記錄 project_id 唔同 → 唔中
            if ((int)$activity_record["project_id"] !== $scope_project_id) {
                return false;
            }
        }

        // 判定：帶 chat_id → 必須相同
        if (isset($activity_scope["chat_id"])) {
            $scope_chat_id = (int)$activity_scope["chat_id"];

            // 判定：記錄 chat_id 唔同 → 唔中
            if ((int)$activity_record["chat_id"] !== $scope_chat_id) {
                return false;
            }
        }

        return true;
    }

    // 寫入頁面活動（鍵＝activity_name＋scope〔project_id／chat_id——0 照計〕；scope 收 id 直接值或語境字串〔chat_sid／project_slug——自動解析〕；同鍵覆寫、無則新增）
    public function set_activity($activity_name, $activity_bundle, $activity_scope = [])
    {
        // scope 取值（id 直接值先落底——未帶項目給 0）
        $project_id = isset($activity_scope["project_id"]) ? (int)$activity_scope["project_id"] : 0;
        $chat_id    = isset($activity_scope["chat_id"]) ? (int)$activity_scope["chat_id"] : 0;

        // 判定：chat_id 仍未有（未帶直接值）→ 藉 chat_sid 揾 chat row（一次拎齊 project_id／chat_id）
        if ($chat_id === 0) {
            $chat_sid = isset($activity_scope["chat_sid"]) ? $activity_scope["chat_sid"] : "";
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

            // 判定：揾到 chat row → 以該對話嘅正式歸屬覆寫
            if ($chat_row) {
                $project_id = (int)$chat_row["project_id"];
                $chat_id    = (int)$chat_row["chat_id"];
            }
        }

        // 判定：project_id 仍未有（chat row 揾唔到）→ 藉 project_slug 查表解（get_project_row——空值／唔中回 null）
        if ($project_id === 0) {
            $project_slug = isset($activity_scope["project_slug"]) ? $activity_scope["project_slug"] : "";
            $project_row  = sc_ai_projects()->project()->get_project_row($project_slug);

            // 判定：解析到 project row → 寫入 project_id
            if ($project_row) {
                $project_id = (int)$project_row["project_id"];
            }
        }

        // 判定：project_id／chat_id 皆 0 → 全域活動（寫單 object）
        $is_global_activity = ($project_id === 0 && $chat_id === 0);

        $activity_file    = $this->get_activity_file($activity_name);
        $activity_records = $this->read_activity_records($activity_file);
        // 本次記錄（同鍵比對用 project_id／chat_id；寫入時間記 activity_at）
        $activity_record = [
            "project_id"       => $project_id,
            "chat_id"          => $chat_id,
            "activity_content" => $activity_bundle,
            "activity_at"      => date("Y-m-d H:i:s"),
        ];

        $activity_record_scope = ["project_id" => $project_id, "chat_id" => $chat_id];

        // 搵同鍵既有記錄（有＝覆寫、無＝新增）
        $matched_record_index = null;

        // 逐筆記錄找同鍵
        foreach ($activity_records as $record_index => $record_item) {
            // 判定：同鍵 → 記下位置、停搵
            if ($this->is_activity_scope_match($record_item, $activity_record_scope)) {
                $matched_record_index = $record_index;
                break;
            }
        }

        // 判定：搵到既有記錄 → 原位覆寫
        if ($matched_record_index !== null) {
            $activity_records[$matched_record_index] = $activity_record;
            $this->write_activity_records($activity_file, $activity_records, $is_global_activity);

            return;
        }

        // 無既有記錄 → 追加（同檔多筆 scope 記錄）
        $activity_records[] = $activity_record;

        $this->write_activity_records($activity_file, $activity_records, $is_global_activity);
    }

    // 讀取頁面活動（activity_name 精確查＋scope 對記錄篩選——查無回 null；有 → 回 bundle）
    public function get_activity($activity_name, $activity_scope = [])
    {
        $activity_file    = $this->get_activity_file($activity_name);
        $activity_records = $this->read_activity_records($activity_file);

        // 逐筆記錄找符合 scope 嘅第一筆
        foreach ($activity_records as $record_item) {
            // 判定：唔符合 scope → 下一筆
            if (!$this->is_activity_scope_match($record_item, $activity_scope)) {
                continue;
            }

            return $record_item["activity_content"];
        }

        return null;
    }
}
