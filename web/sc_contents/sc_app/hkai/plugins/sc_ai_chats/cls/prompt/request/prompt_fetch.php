<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt prompt_fetch：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：prompt_fetch 端點（回傳三格 HTML）） ==
// == 2026-09-27 歸 request 層（POST 端點）；自 prompt/view.php 分獨立檔（one file one function） ==

trait prompt_fetch
{

// POST prompt_fetch：回傳 prompt 區塊內裏整包 HTML（外層 .sc_composer 頁面長駐——前端只 insert 呢一包）
// 專案判定（2026-09-29）：project_slug 空白／查唔到＝唔喺專案頁——composer 回空白（唔渲染輸入區、唔顯示錯誤）
// == 2026-10-07 歸檔專案唔收輸入：project_slug 歸檔形（archive@<slug>／archive）→ composer 空白（對照歸檔對話同一做法） ==
public function prompt_fetch()
{
    $input_data = sc_request()->json();
    
    $project_slug = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
    $chat_sid     = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

    // 判定：歸檔專案（專案段歸檔形 archive@<project_slug>／列面 archive）→ composer 空白（歸檔唔收輸入——判定位喺 is_archive_project_key 唯一住處）
    if (sc_ai_projects()->project()->is_archive_project_key($project_slug)) {
        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "composer" => "",
            ],
        ]);
    }

    // 專案判定（空白／查唔到＝唔喺專案頁——唔查對話列）
    $project_row = null;

    // 專案段查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值）
    $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

    // 判定：有 project_slug → 查專案列
    if ($project_slug !== "") {
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);
    }

    // 判定：冇有效專案 → composer 空白（唔渲染輸入區、唔顯示錯誤）
    if ($project_row === null) {
        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "composer" => "",
            ],
        ]);
    }
    
    // 判定：歸檔語境（歸檔列面 archive 關鍵字——未選中任何對話）→ composer 空白（唔渲染輸入區、唔顯示錯誤）
    if (sc_ai_chats()->chat()->is_archive_chat_segment($chat_sid)) {
        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "composer" => "",
            ],
        ]);
    }

    // 對話 sid 分類（空白＝新對話／valid＝有效對話 sid／查唔到＝invalid——回錯誤 composer）
    $chat_sid_state = "valid";

    // 判定：空白（URL 冇對話段）→ 新對話（唔查對話列）
    if ($chat_sid === "") {
        $chat_sid_state = "new";
    }

    $chat_row = null;

    // 判定：有效對話 sid → 查對話列
    if ($chat_sid_state === "valid") {
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 判定：查唔到（錯 sid）→ state 轉 invalid（回錯誤 composer）
        if ($chat_row === null) {
            $chat_sid_state = "invalid";
        }
    }

    // 判定：已歸檔對話（唯讀語境——冇 prompt 可用；顯示端只讀訊息）→ composer 空白（唔渲染輸入區、唔顯示錯誤）
    if (
        $chat_row !== null
        && $chat_row["chat_status"] !== "normal"
    ) {
        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "composer" => "",
            ],
        ]);
    }

    // 判定：錯 sid（invalid）→ 錯誤 composer（顯示錯誤訊息——唔渲染輸入區）
    if ($chat_sid_state === "invalid") {
        $composer_html = sc_ai_chats()->prompt()->render_chat_prompt_error();
    } else {
        // 新對話（空白）／有效對話 → 照渲染
        $composer_html = sc_ai_chats()->prompt()->render_chat_prompt($project_slug, $chat_sid);
    }
    
    return sc_response()->json([
        "ok"   => true,
        "html" => [
            "composer" => $composer_html,
        ],
    ]);
}
}
