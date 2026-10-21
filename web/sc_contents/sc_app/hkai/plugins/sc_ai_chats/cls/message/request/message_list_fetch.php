<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\request;

// == sc_ai_chats message message_list_fetch：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 歸 request 層（POST 端點）；自 message/view.php 分獨立檔（one file one function） ==

trait message_list_fetch
{

    // POST message_list_fetch：回傳該對話的訊息窗口兩層資料（逐條 template＋slots——前端先插 template 外殼、再灌 slots；窗口以 message_id（keyset 游標）／message_limit 定位；取資料端點——一律 200，成敗由內容 ok 決定；2026-09-23 自 fetch_Class 搬入）
    // 專案判定（2026-09-29）：project_slug 空白／查唔到＝唔喺專案頁——空狀態「請選擇專案」（2026-09-29 由「請開始對話」分家）
    // 窗口界（2026-10-03 keyset）：message_count＝全對話訊息數（chats.chat_message_count）；message_id＝游標（帶＝取更舊一頁＝message_id < 游標；唔帶＝尾段最新窗口）；message_limit＝本窗口條數；has_more＝仲有更舊（後台多取一條判定——1／0）；回傳 message_id＝本窗最舊一條（下一輪更舊游標；空窗＝0）
    // 各狀態計數（2026-09-29）：completed_count／failed_count／cancelled_count／polling_count——直接讀 chats 欄（completed_msg 等——delta 由 update_db_message_status 維護；零 messages 掃描）
    // 對話 sid 分類（2026-09-29）：chat_sid_state＝normal（查得到有效對話列）／""（空字串——空白／錯 sid；前端據此決定起唔起 poll）
    public function message_list_fetch()
    {
        $input_data = sc_request()->json();

        $project_slug  = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid      = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";
        $message_id = isset($input_data["message_id"]) ? (int)$input_data["message_id"] : null;

        // 每頁條數以 plugin 設定為準（不信任前端帶入值）
        $message_limit = $this->get_message_page_size();

        // 專案判定（空白／查唔到＝唔喺專案頁——唔查對話列）
        $project_row = null;

        // 專案段查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值）
        $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

        // 判定：有 project_slug → 查專案列
        if ($project_slug !== "") {
            $project_row = sc_ai_projects()->project()->get_project_row($project_slug);
        }

        // home 語境（空 slug／home 段）→ 放行（對話按 chat_sid 查——project_id 0）
        $is_home_context = ($project_slug === "" || $project_slug === "home");

        // 判定：冇有效專案且唔係 home → 空狀態（窗口界照出＝count 0、無起訖）
        if ($project_row === null && $is_home_context === false) {
            $empty_state_html = "";

            return sc_response()->json([
                "ok"              => true,
                "html"            => ["messages" => $empty_state_html],
                "chat_sid"        => "",
                "chat_sid_state"  => "",
                "message_count"   => 0,
                "has_more"        => 0,
                "message_id"      => 0,
                "message_limit"   => $message_limit,
                "completed_count" => 0,
                "failed_count"    => 0,
                "cancelled_count" => 0,
                "polling_count"   => 0,
            ]);
        }

        // 對話 sid 分類（二分）：normal＝查得到有效對話列（chat_row）／""（空字串）＝其餘（空白／錯 sid——一律當未選）
        $chat_sid_state = "";

        $chat_row = null;

        // 判定：非空白 → 查對話列
        if ($chat_sid !== "") {
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        }

        // 判定：查得到有效對話列 → normal
        if ($chat_row !== null) {
            $chat_sid_state = "normal";
        }

        // 空（$chat_sid_state＝""：空白／錯 sid——合法狀態，不是錯誤；共用空狀態元件，顯隱永遠顯示；窗口界照出＝count 0、無起訖）
        if ($chat_row === null) {
            $empty_state_html = sc_empty_state(hkai__i18n()->get("empty.start_chat"), false);

            return sc_response()->json([
                "ok"              => true,
                "html"            => ["messages" => $empty_state_html],
                "chat_sid"        => "",
                "chat_sid_state"  => $chat_sid_state,
                "message_count"   => 0,
                "has_more"        => 0,
                "message_id"      => 0,
                "message_limit"   => $message_limit,
                "completed_count" => 0,
                "failed_count"    => 0,
                "cancelled_count" => 0,
                "polling_count"   => 0,
            ]);
        }

        $chat_id = (int)$chat_row["chat_id"];

        // 全對話數（窗口界——ttl count）
        $message_count = (int)$chat_row["chat_message_count"];

        // 訊息窗口（keyset：唔帶游標＝尾段最新窗口；帶＝更舊一頁——後台多取一條判 has_more）
        $message_rows = sc_ai_chats()->message()->repo_select()->get_message_rows_window($chat_id, $message_id, $message_limit);

        // 多取一條判定（count > limit＝多出最舊嗰條＝仲有更舊；多出嗰條唔回）
        $has_more = 0;
        if (count($message_rows) > $message_limit) {
            $has_more = 1;
            array_shift($message_rows);
        }

        // 本窗游標（最舊一條——下一輪「更舊」keyset 游標；空窗＝0）
        $window_cursor = 0;
        if (count($message_rows) > 0) {
            $window_cursor = (int)$message_rows[0]["message_id"];
        }

        // 各狀態計數（直接讀 chats 欄——completed_msg／failed_msg／cancelled_msg／polling_msg）
        $completed_count = (int)$chat_row["completed_msg"];
        $failed_count    = (int)$chat_row["failed_msg"];
        $cancelled_count = (int)$chat_row["cancelled_msg"];
        $polling_count   = (int)$chat_row["polling_msg"];

        // 窗口內回應段（只取本頁 message_id——不讀全對話）
        $message_response_rows = [];
        if (count($message_rows) > 0) {
            $message_ids = [];
            foreach ($message_rows as $message_row_item) {
                $message_ids[] = $message_row_item["message_id"];
            }

            $message_response_rows = sc_ai_chats()->message()->repo_select()->get_message_response_rows_by_message_id($message_ids);
        }

        $message_response_rows = array_group_by($message_response_rows, "message_id");

        // 窗口兩層資料（單一渲染源——本 class；逐條 {template, slots}——唔回填，內容交前端灌入）
        $message_entries = sc_ai_chats()->message()->view()->render_chat_message_list($message_rows, $message_response_rows, true);

        return sc_response()->json([
            "ok"              => true,
            "html"            => ["messages" => $message_entries],
            "chat_sid"        => $chat_sid,
            "chat_sid_state"  => $chat_sid_state,
            "message_count"   => $message_count,
            "has_more"        => $has_more,
            "message_id"      => $window_cursor,
            "message_limit"   => $message_limit,
            "completed_count" => $completed_count,
            "failed_count"    => $failed_count,
            "cancelled_count" => $cancelled_count,
            "polling_count"   => $polling_count,
        ]);
    }

}
