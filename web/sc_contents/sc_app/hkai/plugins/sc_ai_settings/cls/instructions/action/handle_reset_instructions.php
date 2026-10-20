<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\action;

// == sc_ai_settings instructions handle_reset_instructions：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==
// == 職責：Reset（刪走 storage 自訂版——重載即讀 manifest 出廠默認） ==

trait handle_reset_instructions
{
    // POST settings_reset_instructions handler（Reset 掣——刪走 storage 自訂版；無自訂版＝no-op）
    public function handle_reset_instructions()
    {
        $request_data      = sc_request()->json();
        $instructions_name = trim($request_data["instructions_name"] ?? "");

        // 判定：instructions_name 不合法 → 400（防未知檔名）
        if (!$this->validate_instructions_name($instructions_name)) {
            return sc_response()->json(["ok" => false, "error" => "invalid instructions name"], 400);
        }

        $paths = $this->get_instructions_paths($instructions_name);

        // 判定：storage 自訂版存在 → 刪走（重載讀 manifest 出廠默認）
        if (is_file($paths["storage_file"])) {
            @unlink($paths["storage_file"]);
        }

        return sc_response()->json(["ok" => true]);
    }
}
