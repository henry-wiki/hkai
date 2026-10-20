<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\view;

// == sc_ai_settings instructions fetch_system_env：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait fetch_system_env
{
    // GET fetch_system_env handler（storage 自訂版優先；無＝讀 manifest 出廠默認（default 狀態——唔 copy）→ 出頁面）
    public function fetch_system_env()
    {
        $paths           = $this->get_instructions_paths("system_env");
        $file_content    = "";
        $file_updated_at = "";

        // 判定：storage 自訂版存在 → 讀現值＋記最後更新時間；否則 → 讀 manifest 出廠默認
        if (is_file($paths["storage_file"])) {
            $file_content    = (string)@file_get_contents($paths["storage_file"]);
            $file_updated_at = date("Y-m-d H:i:s", (int)@filemtime($paths["storage_file"]));
        } else {
            // 判定：manifest 存在 → 讀出廠默認內容
            if (is_file($paths["manifest_file"])) {
                $file_content = (string)@file_get_contents($paths["manifest_file"]);
            }
        }

        // 外部旗標：item 可否編輯（config editable——頁面據此決定出唔出編輯介面）
        $is_editable = $this->instructions_is_editable("system_env");

        $html = $this->get_system_env($file_content, $file_updated_at, $is_editable);

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
