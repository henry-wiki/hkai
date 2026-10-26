<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\action;

// == sc_ai_settings instructions handle_restore_instructions：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait handle_restore_instructions
{
    // POST restore instructions handler（還原默認——manifest 出廠版覆寫 storage 工作檔）
    public function handle_restore_instructions()
    {
        $request_data      = sc_request()->json();
        $instructions_name = trim($request_data["instructions_name"] ?? "");

        // 判定：instructions_name 不合法 → 400（防未知檔名）
        if (!$this->validate_instructions_name($instructions_name)) {
            return sc_response()->json(["ok" => false, "error" => "invalid instructions name"], 400);
        }

        // 由 manifest 覆寫 storage（交 instruction 域）
        $is_synced = hkai()->process()->instruction()->sync_instructions($instructions_name);

        // 判定：manifest 出廠默認不存在／copy 失敗 → 500（無法還原）
        if ($is_synced !== true) {
            return sc_response()->json(["ok" => false, "error" => "manifest default not found"], 500);
        }

        return sc_response()->json(["ok" => true]);
    }
}
