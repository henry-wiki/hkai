<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\request;

// == sc_ai_status_bar status_bar status_bar_fetch：status bar 區塊端點（trait——掛入 status_bar_Class；一檔一動作） ==
// == 2026-10-04 三區動態載入（URL 驅動）：前端逐區 fetch——同一 render_region 路徑砌返對應區 HTML（零重複渲染邏輯） ==

trait status_bar_fetch
{
    // POST status_bar_fetch：收 region（project／chat／workspace）＋頁面段（project_slug／chat_sid 原值）→ 回該區 status bar HTML
    public function status_bar_fetch()
    {
        $input_data = sc_request()->json();

        $region_key   = isset($input_data["region"]) ? $input_data["region"] : "";
        $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid     = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        // 判定：region 唔存在（用戶輸入——必須驗證；白名單＝groups 鍵）→ 回錯誤
        if (!isset($this->groups[$region_key])) {
            return sc_response()->json(["ok" => false, "error" => "invalid region"], 400);
        }

        // 情境還原（provider closures 讀 $_SERVER["REQUEST_URI"] 判當前頁——請求情境暫時換成頁面 URL；render 完還原）
        $page_uri = "/" . $project_slug . "/";

        // 判定：有對話段（含歸檔語境形 archive@<chat_sid>）→ 補入頁面 URL
        if ($chat_sid !== "") {
            $page_uri .= $chat_sid . "/";
        }

        $previous_request_uri   = $_SERVER["REQUEST_URI"];
        $_SERVER["REQUEST_URI"] = $page_uri;

        $region_html = sc_ai_status_bar()->status_bar()->render_region($region_key);

        // 情境還原（交還請求自身嘅 URL 情境）
        $_SERVER["REQUEST_URI"] = $previous_request_uri;

        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "status_bar" => $region_html,
            ],
        ]);
    }
}
