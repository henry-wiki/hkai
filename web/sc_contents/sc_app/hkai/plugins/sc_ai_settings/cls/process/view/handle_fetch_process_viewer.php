<?php

namespace sc\app\hkai\plugins\sc_ai_settings\process\view;

// == sc_ai_settings process handle_fetch_process_viewer：process 域動作（trait——掛入 process_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat fetch_Class 分遷入（one file one action） ==

trait handle_fetch_process_viewer
{
    // GET fetch_process_viewer handler（Process Viewer 分頁——後端渲染）
    // 回固定契約：{ok, keep_auto_refresh, html:{template:{header,body,footer}, slots:{summary,rows}}}
    // template＝固定外殼（含 data-slot 佔位）；slots＝動態內容（前端載入時填、靜默更新時只換這幾塊）
    // keep_auto_refresh 在回應根層（false＝前端停鏈、不再抓）
    public function handle_fetch_process_viewer()
    {
        $modal = $this->get_process_viewer_content();

        // 是否繼續自動更新（true＝前端續 fetch；false＝不再 fetch）——前端讀法：if (result.html.keep_auto_refresh)
        $keep_auto_refresh = true;

        return sc_response()->json(["ok" => true, "keep_auto_refresh" => $keep_auto_refresh, "html" => $modal]);
    }
}
