<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt request resolve_prompt_from_draft：prompt 域解析（trait——掛入 prompt_Class；檔＝動作） ==
// == 職責：草稿 meta → prompt context（sid／內容／model／mode／effort／附件數；meta 為唯一來源） ==
// == 上游：prepare_prompt_request 工序 1；下游：draft get_draft_meta／prompt get_prompt_meta／chat_option get_chat_mode ==

trait resolve_prompt_from_draft
{
    // 由草稿 meta 砌 prompt 欄位（未帶 sid 即場生成——附件落點＋建對話用同一值）
    public function resolve_prompt_from_draft($context)
    {
        // 判定：未帶 chat_sid（未經 prompt_fetch）→ 先生成，後面讀草稿要用同一個值
        if ($context["chat_sid"] === "") {
            // chat_sid 用 UUIDv7（與 prompt_fetch 同一生成來源）
            $context["chat_sid"] = sc_uuid7();
        }

        $draft_meta = sc_ai_chats()->draft()->get_draft_meta($context["project_slug"], $context["chat_sid"]);

        // 判定：草稿未提交任何東西 → 400（工序 1 終止）
        if (count((array)$draft_meta) < 1) {
            $context["error"]        = ["ok" => false, "error" => "no draft found"];
            $context["error_status"] = 400;

            hkai()->runtime()->logger()->warn("prompt_send 工序1: 冇草稿 project_slug=" . $context["project_slug"] . " chat_sid=" . $context["chat_sid"]);

            return $context;
        }

        // 草稿身份（上帶時做目錄名——queue/<slug>/<chat_sid>/<prompt_id>）
        $prompt_id = (string)($draft_meta["prompt_id"] ?? "");

        // 判定：草稿冇 prompt_id → 400（唔准即場補值——上帶目錄名必須同草稿一致）
        if ($prompt_id === "") {
            $context["error"]        = ["ok" => false, "error" => "draft missing prompt_id"];
            $context["error_status"] = 400;

            hkai()->runtime()->logger()->error("prompt_send 工序1: 草稿缺 prompt_id chat_sid=" . $context["chat_sid"]);

            return $context;
        }

        $context["prompt_id"] = $prompt_id;

        // 去向（草稿已記錄——後續建對話列／派工都用呢兩項）
        $context["project_id"]   = (int)($draft_meta["project_id"] ?? 0);
        // 路徑以「當前專案列」為真相（草稿可能係舊路徑——專案搬過／路徑改過就唔可以再用舊值）
        $current_project_row  = sc_ai_projects()->project()->get_project_row($context["project_slug"]);
        $current_project_path = "";

        // 判定：查得到專案列 → 用當前路徑
        if ($current_project_row !== null) {
            $current_project_path = (string)($current_project_row["project_path"] ?? "");
        }

        // 判定：當前專案列冇路徑（異常）→ 退回草稿值
        if ($current_project_path === "") {
            $current_project_path = (string)($draft_meta["project_path"] ?? "");
        }

        $context["project_path"] = $current_project_path;

        // 標題（草稿寫入時已算好——呢度只取）
        $context["chat_title"] = (string)($draft_meta["chat_title"] ?? "");

        $prompt_meta = sc_ai_chats()->prompt()->get_prompt_meta($draft_meta);

        $context["content"]          = $prompt_meta["content"];
        $context["provider_id"]      = $prompt_meta["provider_id"];
        $context["model_id"]         = $prompt_meta["model_id"];
        $context["chat_mode"]        = $prompt_meta["chat_mode"];
        $context["reasoning_effort"] = $prompt_meta["reasoning_effort"];

        // 草稿附件清單（建對話標題 fallback 用；搬檔喺上帶打包時做）
        $context["draft_attachment_items"] = (array)($draft_meta["attachments"] ?? []);

        // 附件數（第一關驗證用——此時仲未搬檔）
        $context["draft_attachment_count"] = count($context["draft_attachment_items"]);

        // chat mode 正規化（DB 與 conf 同一值——單一事實來源）
        $context["conf_chat_mode"] = sc_ai_chats()->chat_option()->get_chat_mode($context["chat_mode"]);

        // prompt meta 收正規化 mode（交付用）；留低俾 enqueue 砌 conf
        $prompt_meta["chat_mode"] = $context["conf_chat_mode"];
        $context["prompt_meta"]   = $prompt_meta;

        return $context;
    }
}
