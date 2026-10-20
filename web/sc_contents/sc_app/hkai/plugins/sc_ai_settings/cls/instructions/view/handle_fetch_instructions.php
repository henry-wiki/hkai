<?php

namespace sc\app\hkai\plugins\sc_ai_settings\instructions\view;

// == sc_ai_settings instructions handle_fetch_instructions：instructions 域動作（trait——掛入 instructions_Class；檔＝動作） ==

trait handle_fetch_instructions
{
    // GET fetch_instructions handler（保證 storage 工作檔存在——首次由 manifest copy；讀現值 → 出頁面）
    public function handle_fetch_instructions()
    {
        $request_data      = sc_request()->json();
        $instructions_name = trim($request_data["instructions_name"] ?? "");

        // 判定：instructions_name 不合法 → 400（防未知檔名）
        if (!$this->validate_instructions_name($instructions_name)) {
            return sc_response()->json(["ok" => false, "error" => "invalid instructions name"], 400);
        }

        $this->ensure_instructions_file($instructions_name);

        $paths        = $this->get_instructions_paths($instructions_name);
        $file_content = "";

        // 判定：storage 工作檔存在 → 讀現值
        if (is_file($paths["storage_file"])) {
            $file_content = (string)@file_get_contents($paths["storage_file"]);
        }

        $html = $this->get_modal_content($instructions_name, $file_content);

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
