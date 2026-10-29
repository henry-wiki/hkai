<?php

namespace sc\app\hkai\plugins\sc_ai_health\stream;

// == sc_ai_health health stream create_viewer_snapshot：每個頁面一個參照檔（trait——掛入 health_Class） ==
// == 上游：sc_app/hkai/index.php（頁面入口）；下游：data/stream/stream.json（真實版本）＋data/stream/viewers/ ==
// == 行為：① 清走逾 5 分鐘冇心跳的 viewer 檔 ② 開新 uuidv7 ③ 複製 stream.json 當刻內容入新檔 ==

trait create_viewer_snapshot
{
    // 建立本頁 viewer 檔（回傳 uuidv7；失敗回 ""）
    public function create_viewer_snapshot()
    {
        $viewer_dir = sc_get_config("hkai.stream_dir") . "/viewers";

        // 判定：viewer 目錄唔存在 → 建立
        if (is_dir($viewer_dir) === false) {
            @mkdir($viewer_dir, 0777, true);
        }

        $this->sweep_expired_viewer_files();

        $viewer_id  = self::_make_uuid_v7();
        $viewer_file = $viewer_dir . "/" . $viewer_id . ".json";

        $viewer_data               = [];
        $viewer_data["created_at"] = date("Y-m-d H:i:s");
        $viewer_data["updated_at"] = date("Y-m-d H:i:s");
        $viewer_data["targets"]    = new \stdClass();

        $viewer_json = json_encode($viewer_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $written = @file_put_contents($viewer_file, $viewer_json);

        // 判定：寫入失敗 → 回空
        if ($written === false) {
            return "";
        }

        return $viewer_id;
    }

    // 清走逾 5 分鐘冇心跳的 viewer 檔（回傳清走數量）
    // 清走逾 5 分鐘冇心跳的 viewer 檔（頁面入口同 SSE 迴圈都會叫）
    public function sweep_expired_viewer_files()
    {
        $viewer_dir      = sc_get_config("hkai.stream_dir") . "/viewers";
        $expired_seconds = 300;
        $removed_count   = 0;
        $now_time        = time();

        foreach (glob($viewer_dir . "/*.json") as $viewer_path) {
            $viewer_stat = @stat($viewer_path);

            // 判定：stat 失敗 → 跳過
            if ($viewer_stat === false) {
                continue;
            }

            $idle_seconds = $now_time - (int)$viewer_stat["mtime"];

            // 判定：未逾時 → 保留
            if ($idle_seconds <= $expired_seconds) {
                continue;
            }

            $removed = @unlink($viewer_path);

            // 判定：刪除成功 → 計數
            if ($removed === true) {
                $removed_count = $removed_count + 1;
            }
        }

        return $removed_count;
    }

    // 產生 UUID v7（RFC 9562：48-bit 毫秒時間戳＋版本 7＋隨機）
    private static function _make_uuid_v7()
    {
        $milliseconds = (int)floor(microtime(true) * 1000);

        $time_hex = str_pad(dechex($milliseconds), 12, "0", STR_PAD_LEFT);
        $random_bytes = random_bytes(10);

        $random_hex = bin2hex($random_bytes);

        // 無連字符 32 hex（檔名用）：48-bit 時間＋版本 7＋variant＋隨機
        $time_part    = str_pad(dechex($milliseconds), 12, "0", STR_PAD_LEFT);
        $version_part = "7" . substr($random_hex, 0, 3);
        $variant_part = dechex(8 + (hexdec(substr($random_hex, 3, 1)) % 4)) . substr($random_hex, 4, 3);

        return $time_part . $version_part . $variant_part . substr($random_hex, 7, 12);
    }
}
