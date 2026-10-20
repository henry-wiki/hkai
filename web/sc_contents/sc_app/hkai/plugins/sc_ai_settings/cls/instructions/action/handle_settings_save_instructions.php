<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\action;

// == sc_ai_settings instructions handle_settings_save_instructions：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait handle_settings_save_instructions
{
    // POST save instructions handler（modal 編輯 → 寫入 storage 工作檔——manifest 永不寫）
    public function handle_settings_save_instructions()
    {
        $request_data      = sc_request()->json();
        $instructions_name = trim($request_data["instructions_name"] ?? "");
        $content           = (string)($request_data["content"] ?? "");

        // 判定：instructions_name 不合法 → 400（防未知檔名）
        if (!$this->validate_instructions_name($instructions_name)) {
            return sc_response()->json(["ok" => false, "error" => "invalid instructions name"], 400);
        }

        // 判定：item 唔可編輯（config editable=0）→ 400（後台話事——唔可更新）
        if (!$this->instructions_is_editable($instructions_name)) {
            return sc_response()->json(["ok" => false, "error" => "instructions not editable"], 400);
        }

        $this->ensure_instructions_file($instructions_name);

        $paths = $this->get_instructions_paths($instructions_name);

        file_put_contents($paths["storage_file"], $content, LOCK_EX);

        return sc_response()->json(["ok" => true]);
    }
}
