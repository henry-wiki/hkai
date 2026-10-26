<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\view;

// == sc_ai_settings instructions fetch_system_env：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait fetch_system_env
{
    // GET fetch_system_env handler（storage 自訂版優先；無＝讀 manifest 出廠默認（default 狀態——唔 copy）→ 出頁面）
    public function fetch_system_env()
    {
        // 讀現值（storage 優先、無＝manifest 出廠默認——交 instruction 域）
        $read_result     = hkai()->process()->instruction()->read_instructions("system_env");
        $file_content    = $read_result["content"];
        $file_updated_at = $read_result["updated_at"];

        // 外部旗標：item 可否編輯（config editable——頁面據此決定出唔出編輯介面）
        $is_editable = $this->instructions_is_editable("system_env");

        $html = $this->get_system_env($file_content, $file_updated_at, $is_editable);

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
