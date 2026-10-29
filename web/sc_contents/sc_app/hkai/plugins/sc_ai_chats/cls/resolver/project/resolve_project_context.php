<?php

namespace sc\app\hkai\plugins\sc_ai_chats\resolver\project;

// == sc_ai_chats resolver\project resolve_project_context：專案解析（trait——掛入 sc_ai_chats_Class；檔＝動作） ==
// == 職責：prompt context 嘅專案四欄（project_row／project_slug／project_id／project_path）；Home 都冇＝記錯 ==
// == 上游：prompt_send()->prepare_prompt_request；下游：resolver\project resolve_project_row ==

trait resolve_project_context
{
    // 由專案段砌 prompt context（fallback 規則住 resolve_project_row）
    public function resolve_project_context($context)
    {
        $project_row = sc_ai_chats()->resolve_project_row($context["project_slug"]);

        // 判定：連 Home 都查唔到（資料壞）→ 記錄錯誤
        if ($project_row === null) {
            $context["error"] = ["ok" => false, "error" => "home project not found"];
            return $context;
        }

        $context["project_row"]  = $project_row;
        $context["project_slug"] = (string)$project_row["project_slug"];
        $context["project_id"]   = (int)$project_row["project_id"];
        $context["project_path"] = (string)$project_row["project_path"];

        return $context;
    }
}
