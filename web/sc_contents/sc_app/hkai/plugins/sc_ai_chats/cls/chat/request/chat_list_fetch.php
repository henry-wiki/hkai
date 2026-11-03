<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\request;

// == sc_ai_chats chat chat_list_fetch：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 歸 request 層（POST 端點）；自 web/sc_contents/sc_app/hkai/plugins/sc_ai_settings/cls/view/view.php 分獨立檔（one file one function） ==

trait chat_list_fetch
{

    // POST 取對話清單 HTML（取資料端點——一律 200，成敗由內容 ok 決定；2026-09-23 自 fetch_Class 搬入）
    public function chat_list_fetch()
    {
        $input_data = sc_request()->json();
        
        $project_slug   = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $input_chat_sid = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : null;

        // 清單語境旗標（歸檔兩源：對話歸檔＝archive／archive@<chat_sid>；專案歸檔＝archive@<project_slug>／archive——前端據此上 .sc_chat_list.archived／.sc_sidebar--chats.archived；旗標由後台出）
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
        
        // 清單一律出齊（正常＋歸檔；顯示由 CSS 按 .sc_chat_list.archived 決定）
        $resolved_chat_sid = $input_chat_sid;

        // 判定：歸檔語境形（archive@<chat_sid>）→ 剝走前綴，取純 sid 作高亮用
        if (sc_ai_chats()->chat()->is_archive_chat_segment($input_chat_sid)) {
            $resolved_chat     = sc_ai_chats()->chat()->resolve_chat_sid($input_chat_sid);
            $resolved_chat_sid = $resolved_chat["chat_sid"];
        }

        // 目前對話解析（無效 → null——清單照出、冇一個 active）
        $selected_chat_sid = sc_ai_chats()->chat()->resolve_selected_chat_sid($project_row, $resolved_chat_sid);

        $chat_list_html = sc_ai_chats()->chat()->render_chat_list($project_slug, $selected_chat_sid);

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
