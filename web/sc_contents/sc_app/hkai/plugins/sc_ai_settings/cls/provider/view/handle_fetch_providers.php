<?php

namespace sc\app\hkai\plugins\sc_ai_settings\provider\view;

// == sc_ai_settings provider handle_fetch_providers：provider 域動作（trait——掛入 provider_Class；檔＝動作） ==
// == 職責：Provider 分頁（後端渲染——一頁列全部 provider 輸入欄；回 {ok, html:{header, body, footer}}） ==

trait handle_fetch_providers
{
    // GET fetch_providers handler（Provider 分頁——右欄內容；左選單由 settings 體系維持）
    public function handle_fetch_providers()
    {
        $html = $this->build_provider_settings_page();

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
