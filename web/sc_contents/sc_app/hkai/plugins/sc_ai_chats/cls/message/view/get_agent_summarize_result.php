<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_summarize_result：message 域動作（trait——掛入 view_Class；檔＝動作：摘要 result 盒） ==

trait get_agent_summarize_result
{
    // 砌摘要 result 盒（內容住 scroll 內層 div——data-text 掛內層；對稱 response 段）
    public function get_agent_summarize_result($message_row, $response_row)
    {
        // 本型（result——內容／身份／段長／開始時間；食 row）
        $_section_result = trim((string)($response_row["response_content"] ?? ""));
        $_section_result_encoded = sc_html_encode($_section_result);
        $_section_result_response_id         = (string)($response_row["response_id"] ?? "");
        $_section_result_response_status     = (string)($response_row["response_status"] ?? "");
        $_section_result_content_length      = mb_strlen($_section_result);
        $_section_result_response_started_at = (string)($response_row["response_started_at"] ?? "");
        
        // 本型 attribute（勾出成 array、implode 合成——同 response 段寫法）
        $_section_result_attrs = [];
        $_section_result_attrs[] = "data-response_id='" . $_section_result_response_id . "'";
        $_section_result_attrs[] = "data-start_at='" . $_section_result_response_started_at . "'";
        $_section_result_attrs[] = "data-response_status='" . $_section_result_response_status . "'";
        $_section_result_attrs[] = "data-content_length='" . $_section_result_content_length . "'";
        $_section_result_attrs = implode(" ", $_section_result_attrs);
        
        // 段用時（秒——生成中計到當下、完成＝凍結；工具列計時器用）
        $_section_result_elapsed_sec = sc_ai_chats()->message()->view()->get_response_elapsed_sec($response_row);
        
        $_response_cssClass = ["seg", "seg--sum", "seg--sum_result"];
        
        // 判定：本型空 → 隱藏（空盒唔顯示）
        if ($_section_result === "") {
            $_response_cssClass[] = "hidden";
        }
        
        $_response_cssClass = implode(" ", $_response_cssClass);
        
        $content = [];
        $content[] = "<div class='{$_response_cssClass}' {$_section_result_attrs}>";
        $content[] = "<div class='seg__body'>";
            $content[] = sc_ai_chats()->message()->view()->get_agent_summarize_toolbar($_section_result_elapsed_sec, $_section_result_response_id);
            // 內容住 scroll 內層（data-text 掛內層 div；attribute 喺盒）
            $content[] = "<div class='seg__scroll'>";
                $content[] = "<div data-text='sum_result' class='seg__text'>" . $_section_result_encoded . "</div>";
            $content[] = "</div>";
        $content[] = "</div>";
        // 水印字（盒中央——純裝飾；pointer 唔食）
        $content[] = "<div class='seg__mark'><div>summarize result</div></div>";
        $content[] = "</div>";
        
        return implode("", $content);
    }
}
