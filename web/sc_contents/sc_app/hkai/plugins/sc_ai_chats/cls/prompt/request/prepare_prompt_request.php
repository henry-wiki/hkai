<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt prepare_prompt_request：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 職責：工序 1–4 準備階段（草稿 → 必填驗證 → 兩關驗證 → queue 上限）；任何一關失敗即回 context.error ==
// == 上游：prompt_send 工序 1–4；下游：resolver resolve_prompt_from_draft／guard 三支＋assert_queue_capacity ==

trait prepare_prompt_request
{
    // 準備（回 context；error 有值＝工序失敗）
    public function prepare_prompt_request($context)
    {
        // 工序 1：取草稿（meta 全量入 context；冇草稿／唔合法 → error）
        $context = sc_ai_chats()->prompt()->resolve_prompt_from_draft($context);

        // 判定：工序 1 失敗 → 交返
        if ($context["error"] !== null) {
            return $context;
        }

        // 工序 2：必填欄位驗證（有附件可無文字）
        $context = sc_ai_chats()->prompt()->validate_prompt_prerequisites($context);

        // 判定：工序 2 失敗 → 交返
        if ($context["error"] !== null) {
            return $context;
        }

        // 工序 3：三關（伺服器狀態／可處理性／模型供應商）
        $context = sc_ai_chats()->prompt()->validate_prompt_gates($context);

        // 判定：工序 3 失敗 → 交返
        if ($context["error"] !== null) {
            return $context;
        }

        // 工序 4：queue 上限（同對話等待 ≤ 3）
        $context = sc_ai_chats()->prompt()->assert_queue_capacity($context);

        return $context;
    }
}
