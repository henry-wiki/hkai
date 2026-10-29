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

    // 專案段＋對話段解析（歸檔旗標＋純 slug／sid——同一刻做）
    $resolved_project_slug  = sc_ai_projects()->project()->resolve_project_slug($project_slug);
    $project_slug           = $resolved_project_slug["project_slug"];

    $resolved_chat_sid      = sc_ai_chats()->chat()->resolve_chat_sid($chat_sid);
    $chat_sid               = $resolved_chat_sid["chat_sid"];

    // 歸檔語境旗標（專案段或對話段任一個係 archive）
    $is_archive_context = false;

    // 判定：專案歸檔語境 → 旗標
    if ($resolved_project_slug["archive"] === 1) {
        $is_archive_context = true;
    }

    // 判定：對話歸檔語境 → 旗標
    if ($resolved_chat_sid["archive"] === 1) {
        $is_archive_context = true;
    }

    // 判定：歸檔語境 → composer 空白（歸檔唔收輸入）
    if ($is_archive_context === true) {
        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "composer" => "",
            ],
        ]);
    }

    // 專案列（resolve 後 slug 一定非空——空白已 reset 做預設專案）
    $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

    // 判定：專案查唔到（已歸檔／唔存在）→ composer 空白
    if ($project_row === null) {
        return sc_response()->json([
            "ok"   => true,
            "html" => [
                "composer" => "",
            ],
        ]);
    }

    // 格式判定（32 hex＝有效 sid 形；空白＝新對話，未配 sid）
    $is_chat_sid_blank = ($chat_sid === "");
    $is_chat_sid_hex32 = sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid);

    // 判定：有值但格式唔合 → 錯 sid（錯誤 composer）
    if ($is_chat_sid_blank === false && $is_chat_sid_hex32 === false) {
        $composer_html = sc_ai_chats()->prompt()->render_chat_prompt_error();

        return sc_response()->json([
            "ok"           => true,
            "html"         => ["composer" => $composer_html],
            "project_slug" => $project_slug,
        ]);
    }

    // 判定：空白（新對話）→ 後端配 sid
    if ($is_chat_sid_blank === true) {
        // chat_sid 用 UUIDv7（時間排序——列表／檔案次序天然有序）
        $chat_sid = sc_uuid7();
    } else {
        // 已有 sid → 查對話列（查唔到＝新 prompt 預配 sid——照渲染、靠 draft 還原）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);

        // 判定：已歸檔對話（唯讀——冇 prompt 可用）→ composer 空白
        if ($chat_row !== null && $chat_row["chat_status"] !== "normal") {
            return sc_response()->json([
                "ok"   => true,
                "html" => ["composer" => ""],
            ]);
        }
    }

    // 新對話／有效對話 → 照渲染
    $composer_html = sc_ai_chats()->prompt()->render_chat_prompt($project_slug, $chat_sid);

    return sc_response()->json([
        "ok"           => true,
        "html"         => [
            "composer" => $composer_html,
        ],
        "project_slug" => $project_slug,
        "chat_sid"     => $chat_sid,
    ]);
}
}
