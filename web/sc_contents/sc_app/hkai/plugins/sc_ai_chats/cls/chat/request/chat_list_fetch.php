<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\request;

// == sc_ai_chats chat chat_list_fetch：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 歸 request 層（POST 端點）；自 chat/view.php 分獨立檔（one file one function） ==

trait chat_list_fetch
{

    // POST 取對話清單 HTML（取資料端點——一律 200，成敗由內容 ok 決定；2026-09-23 自 fetch_Class 搬入）
    public function chat_list_fetch()
    {
        $input_data = sc_request()->json();
        
        $project_slug   = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $input_chat_sid = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : null;

        // 清單語境旗標（歸檔兩源：對話歸檔＝archive／archive@<chat_sid>；專案歸檔＝archive@<project_slug>／archive——前端據此上 .sc_chat_list.archive／.sc_sidebar--chats.archive；旗標由後台出）
        $is_archive = "0";
        if (sc_ai_chats()->chat()->is_archive_chat_segment($input_chat_sid)) {
            $is_archive = "1";
        }

        // 專案段原值＋解析（歸檔旗標＋純 slug——單一來源）
        $raw_project_slug = $project_slug;
        $resolved_project_slug = sc_ai_projects()->project()->resolve_project_slug($raw_project_slug);
        $project_slug = $resolved_project_slug["project_slug"];

        // 判定：專案歸檔語境 → 清單旗標
        if ($resolved_project_slug["archive"] === 1) {
            $is_archive = "1";
        }

        // Home 語境（未指定專案段）→ 呼叫端 fallback：用固定 home 繼續查（空 slug 本身查唔到嘢）
        if ($project_slug === "") {
            $project_slug = sc_get_config("hkai.default_project_slug");
        }
        
        // 判定：歸檔語境 → 再分「列面」定「單一歸檔專案」
        if ($resolved_project_slug["archive"] === 1) {
            // 判定：純 archive（冇 @）＝列面 → 當未選專案（同 home 共用空狀態）
            if (strpos($raw_project_slug, "@") === false) {
                $chat_list_html = "";
                return sc_response()->json([
                    "ok"         => true,
                    "html"       => ["chat_list" => $chat_list_html, "chat_header_kicker" => ""],
                    "is_archive" => $is_archive,
                ]);
            }
        }

        // 判定：專案要真係存在（唔止有冇傳——slug 查得到先算）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // home 語境（空 slug／home 段）→ 合成虛擬專案列（project_id 0——home 對話）
        if ($project_row === null && ($project_slug === "" || $project_slug === sc_get_config("hkai.default_project_slug"))) {
            $project_row = [
                "project_id"     => 0,
                "project_slug"   => sc_get_config("hkai.default_project_slug"),
                "project_status" => "normal",
                "project_name"   => "",
            ];
        }

        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "project not found"]);
        }
        
        // 判定：URL 對話段係歸檔語境（archive／archive@<chat_sid>）→ 歸檔清單（archive chat list——判定位喺 is_archive_chat_segment 唯一住處）
        if (sc_ai_chats()->chat()->is_archive_chat_segment($input_chat_sid)) {
            // 瀏覽高亮：語境形（archive@<chat_sid>）→ 取純 sid 作 active；純關鍵字（archive——列面）→ 冇高亮
            $selected_chat_sid = null;
            if (strpos($input_chat_sid, "@") !== false) {
                $selected_chat_sid = sc_ai_chats()->chat()->get_bare_chat_sid($input_chat_sid);
            }

            $chat_list_html = sc_ai_chats()->chat()->render_archived_chat_list($project_slug, $selected_chat_sid);
        } else {
            // 目前對話解析（caller 未指定＝兜底揀預設；caller 有指定但無效 → null——清單照出、冇一個 active）
            $selected_chat_sid = sc_ai_chats()->chat()->resolve_selected_chat_sid($project_row, $input_chat_sid);
            
            $chat_list_html = sc_ai_chats()->chat()->render_chat_list($project_slug, $selected_chat_sid);
        }
        
        // header kicker（歸檔語境＝歸檔題示；否則＝目前專案名——連清單同一回應更新）
        $chat_header_kicker = sc_html_encode($project_row["project_name"]);

        // 判定：歸檔語境 → kicker 轉歸檔題示
        if ($is_archive === "1") {
            $chat_header_kicker = hkai()->i18n()->get("chat.archive_kicker");
        }

        $output = [];
        $output["ok"]         = true;
        $output["html"]       = ["chat_list" => $chat_list_html, "chat_header_kicker" => $chat_header_kicker];
        $output["is_archive"] = $is_archive;

        return sc_response()->json($output);
    }

}
