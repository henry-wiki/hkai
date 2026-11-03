<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft save_meta：即時保存草稿資料（trait——掛入 draft_Class） ==
// == 職責：文字／provider／model／reasoning／chat_mode → meta（agent 由後台現行值蓋章） ==
// == 上游：request/draft/draft_save_meta.php；下游：draft_Class update_meta ==

trait save_meta
{

    // POST draft_save_meta：任何欄位有變 → 即時寫 meta
    public function draft_save_meta()
    {
        $input_data = sc_request()->json();

        $project_slug = trim((string)($input_data["project_slug"] ?? ""));
        $chat_sid     = trim((string)($input_data["chat_sid"] ?? ""));

        // 判定：目錄 key 格式唔合 → 400
        if (!sc_ai_chats()->chat()->is_valid_chat_sid($chat_sid)) {
            return sc_response()->json(["ok" => false, "error" => "invalid chat_sid"], 400);
        }

        // 專案列（空／無效 slug → fallback Home）
        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到 → 查 Home
        if ($project_row === null) {
            $project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        // 判定：連 Home 都冇 → 嚴重錯
        if ($project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        $project_slug = (string)$project_row["project_slug"];

        // 現行 meta（內容比對用）
        $current_meta = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);

        // 判定：冇 meta → 當空
        if (is_array($current_meta) === false) {
            $current_meta = [];
        }

        $meta_patch = [];
        $meta_patch["chat_sid"] = $chat_sid;

        // 判定：有帶文字 → 更新
        if (isset($input_data["prompt_text"])) {
            $meta_patch["prompt_text"] = (string)$input_data["prompt_text"];
        }

        // 判定：有帶 provider → 更新
        if (isset($input_data["provider_id"])) {
            $meta_patch["provider_id"] = (string)$input_data["provider_id"];
        }

        // 判定：有帶 model → 更新
        if (isset($input_data["model_id"])) {
            $meta_patch["model_id"] = (string)$input_data["model_id"];
        }

        // 判定：有帶推理等級 → 更新
        if (isset($input_data["reasoning_effort"])) {
            $meta_patch["reasoning_effort"] = (string)$input_data["reasoning_effort"];
        }

        // 判定：有帶 chat mode → 更新
        if (isset($input_data["chat_mode"])) {
            $meta_patch["chat_mode"] = (string)$input_data["chat_mode"];
        }

        // 寫入者（viewer id——同一頁自己寫的變更唔使套回自己畫面）
        if (isset($input_data["viewer_id"])) {
            $meta_patch["viewer_id"] = (string)$input_data["viewer_id"];
        }

        // 判定：內容與現行完全相同 → 不寫（回音防護——rev／mtime 都唔動）
        $changed_field_keys = [];

        foreach ($meta_patch as $patch_field_key => $patch_field_value) {
            // 判定：非內容欄位（由後台蓋章）→ 唔計入比較
            if (in_array($patch_field_key, ["chat_sid", "agent", "is_new_chat"])) {
                continue;
            }

            $current_field_value = (string)($current_meta[$patch_field_key] ?? "");

            // 判定：值有實際改變 → 記入
            if ($current_field_value !== (string)$patch_field_value) {
                $changed_field_keys[] = $patch_field_key;
            }
        }

        // 判定：冇任何內容改變 → 直接回現行版本（唔寫檔、唔遞增 rev）
        if (count($changed_field_keys) < 1) {
            return sc_response()->json([
                "ok"             => true,
                "chat_sid"       => $chat_sid,
                "missing_fields" => $this->_get_missing_prompt_fields($current_meta),
                "unchanged"      => true,
            ]);
        }

        // agent（後台現行值——系統讀嘅 id）
        $meta_patch["agent"] = (string)hkai()->agent()->get_agent();

        // 新對話標記（DB 未有 chat row＝1；有＝0）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        $meta_patch["is_new_chat"] = ($chat_row === null) ? 1 : 0;

        $saved = sc_ai_chats()->draft()->set_draft_meta($project_slug, $chat_sid, $meta_patch);

        // 儲存後檢查（同 gates 同一份清單——只回報缺失，唔阻擋草稿儲存）
        $saved_meta     = sc_ai_chats()->draft()->get_draft_meta($project_slug, $chat_sid);
        $missing_fields = $this->_get_missing_prompt_fields($saved_meta);

        return sc_response()->json([
            "ok"             => ($saved === true),
            "chat_sid"       => $chat_sid,
            "missing_fields" => $missing_fields,
        ]);
    }

    // 缺失必填欄位（清單單一來源住 draft 域；回 key 清單）
    private function _get_missing_prompt_fields($meta_data)
    {
        $required_fields = sc_ai_chats()->draft()->get_prompt_required_fields();

        $missing_fields = [];

        // 逐個必填欄位對照 meta
        foreach ($required_fields as $required_field_item) {
            $required_field_key   = (string)$required_field_item["key"];
            $required_field_value = $meta_data[$required_field_key] ?? null;

            // 判定：未提交／空值 → 記入缺失
            if ($required_field_value === null || $required_field_value === "") {
                $missing_fields[] = $required_field_key;
            }
        }

        return $missing_fields;
    }
}
