<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message build_response_section：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-10-05 新設：DB 兩列（message_row＋response_row）砌 serve 段——配對鍵＝response_id ==
// == 2026-10-05 加：summarize 兩型亦出骨架（空內容＝hidden 殼——前端按內容自動解除） ==

trait build_response_section
{
    // 砌 serve 段（type／content／skeleton_html／response_id 出——骨架／模型字內部自理）
    public function build_response_section($message_row, $response_row)
    {
        $_response_type    = (string)($response_row["response_type"] ?? "");
        $_response_content = (string)($response_row["response_content"] ?? "");
        $_response_status  = (string)($response_row["response_status"] ?? "");
        $_response_id      = (int)($response_row["response_id"] ?? 0);
        
        // 骨架（response 系兩型＋summarize 兩型——error 無骨架）
        $_skeleton = [];
        
        if ($_response_type === "") {
        } elseif ($_response_type === "summarize_thinking") {
            $_skeleton[] = sc_ai_chats()->message()->view()->get_agent_summarize_thinking($message_row, $response_row);
        } elseif ($_response_type === "summarize_result") {
            $_skeleton[] = sc_ai_chats()->message()->view()->get_agent_summarize_result($message_row, $response_row);
        } elseif ($_response_type === "response_thinking") {
            $_skeleton[] = sc_ai_chats()->message()->view()->get_agent_response_thinking($message_row, $response_row);
        } elseif ($_response_type === "response_result") {
            $_skeleton[] = sc_ai_chats()->message()->view()->get_agent_response_result($message_row, $response_row);
        }
        
        // 段用時（秒——計時器每輪基準；無 timing → 0）
        $_response_elapsed_sec = sc_ai_chats()->message()->view()->get_response_elapsed_sec($response_row);

        // 判定：無 timing（null）→ 0
        if ($_response_elapsed_sec === null) {
            $_response_elapsed_sec = 0;
        }

        return [
            "type"          => $_response_type,
            "content"       => $_response_content,
            "skeleton_html" => implode("\r\n", $_skeleton),
            "response_id"   => $_response_id,
            "elapsed_sec"   => $_response_elapsed_sec,
            "status"        => $_response_status,
        ];
    }
}
