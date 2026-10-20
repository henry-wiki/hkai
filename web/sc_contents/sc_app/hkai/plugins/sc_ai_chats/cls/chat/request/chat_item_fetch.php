<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\request;

// == sc_ai_chats chat chat_item_fetch：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-28 單項對話內容端點（前端觀察器鏈——只換 children 用） ==

trait chat_item_fetch
{

    // POST 取單一對話項 HTML（取資料端點——一律 200，成敗由內容 ok 決定）
    public function chat_item_fetch()
    {
        $input_data = sc_request()->json();

        $project_slug      = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid          = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";
        $selected_chat_sid = isset($input_data["selected_chat_sid"]) ? $input_data["selected_chat_sid"] : "";

        // chat_sid 缺 → 內容回報失敗（HTTP 仍 200）
        if ($chat_sid === "") {
            return sc_response()->json(["ok" => false, "error" => "chat_sid is required"]);
        }

        // 查表用純 sid（卡片身份＝段形——歸檔項會帶 archive@ 前綴；同一解析規則）
        $chat_sid = sc_ai_chats()->chat()->get_bare_chat_sid($chat_sid);

        // 判定：對話要真係存在（sid 查得到先算）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        if ($chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "chat not found"]);
        }

        // 轉義一次過（整個列交 sc_html_encode——同清單渲染一致）
        $chat_row_item = sc_html_encode($chat_row);

        // 專案段查表用純 slug（歸檔形 archive@<project_slug> → @ 後；其餘 → 原值）
        $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

        // 上下文專案列（連結第一段來源——歸檔形 archive@<project_slug> 由 build_chat_url 統一砌）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：專案要真係存在（連結第一段無所依 → 內容回報失敗；HTTP 仍 200）
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"]);
        }

        // active 判定用「目前選取」——chat_sid 只係目標（傳 chat_sid ≠ 佢係 selected_chat_sid）
        $chat_item_html = sc_ai_chats()->chat()->render_chat_item($chat_row_item, $project_row, $selected_chat_sid);

        $output = [];
        $output["ok"]   = true;
        $output["html"] = ["chat_item" => $chat_item_html];

        return sc_response()->json($output);
    }

}
