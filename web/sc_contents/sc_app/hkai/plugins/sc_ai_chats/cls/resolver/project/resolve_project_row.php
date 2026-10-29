<?php

namespace sc\app\hkai\plugins\sc_ai_chats\resolver\project;

// == sc_ai_chats resolver\project resolve_project_row：專案解析（trait——掛入 sc_ai_chats_Class；檔＝動作） ==
// == 職責：專案段 → 專案列（查唔到／空 slug → fallback 固定 Home；連 Home 都冇 → null） ==
// == 上游：prompt_send()->resolve_project_context；下游：sc_ai_projects project_Class get_project_row ==

trait resolve_project_row
{
    // 解析專案列（chat／prompt／message 共用同一 fallback 規則）
    public function resolve_project_row($raw_project_slug)
    {
        $project_row = sc_ai_projects()->project()->get_project_row($raw_project_slug);

        // 判定：查唔到（空 slug／無效 slug）→ fallback 查固定 Home 專案
        if ($project_row === null) {
            $project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        return $project_row;
    }
}
