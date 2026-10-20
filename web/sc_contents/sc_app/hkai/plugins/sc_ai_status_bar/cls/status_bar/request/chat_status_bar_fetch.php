<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\request;

// == sc_ai_status_bar status_bar chat_status_bar_fetch：status bar chat 區端點（trait——掛入 status_bar_Class；一檔一動作） ==
// == 2026-10-04 自 status_bar_fetch 拆分（region 參數分派 → 一區一端點；region 固定 "chat"，唔再收前端輸入） ==

trait chat_status_bar_fetch
{
    // POST chat_status_bar_fetch：收頁面段（project_slug／chat_sid 原值）→ 回 chat 區 status bar HTML
    public function chat_status_bar_fetch()
    {
        $input_data = sc_request()->json();

        $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid     = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        $region_html = $this->render_region_for_page("chat", $project_slug, $chat_sid);

        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "status_bar" => $region_html,
            ],
        ]);
    }
}
