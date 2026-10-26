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

        // 保證指示檔就位（交 instruction 域——system_prompt 覆寫、system_env 只補缺）
        hkai()->process()->instruction()->sync_instructions($instructions_name);

        // 讀現值（storage 優先、無＝manifest 出廠默認——交 instruction 域）
        $read_result  = hkai()->process()->instruction()->read_instructions($instructions_name);
        $file_content = $read_result["content"];

        $html = $this->get_modal_content($instructions_name, $file_content);

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
