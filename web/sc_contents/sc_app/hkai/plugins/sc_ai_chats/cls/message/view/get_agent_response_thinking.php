<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_response_thinking：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 message/view.php 分獨立檔（one file one function） ==

trait get_agent_response_thinking
{
    // 渲染 thinking 段 HTML（常開——內容；水印字置中（同摘要款）；內容頂工具列：放大／複製；根元素帶 data-response_id——前端配對用）
    public function get_agent_response_thinking($message_row, $response_row)
    {
        $_section_content = trim((string)($response_row["response_content"] ?? ""));
        $_section_content_encoded = sc_html_encode($_section_content);

        // 段身份／狀態／段長／開始時間（落 attribute——前端起 poll 讀返；長度＝mb 字元——同後台裁剪同一把尺）
        $_section_response_id = (string)($response_row["response_id"] ?? "");
        $_section_status = (string)($response_row["response_status"] ?? "");
        $_section_content_length = mb_strlen($_section_content);
        $_section_response_started_at = (string)($response_row["response_started_at"] ?? "");
        
        
        $_response_cssClass = ["seg", "seg--thinking"];

        if($_section_content === "")
        {
            $_response_cssClass[] = "hidden";
        }
        
        $_response_cssClass = implode(" ",$_response_cssClass);
        
        $_response_attrs = [];
        $_response_attrs[] = "data-response_id='" . $_section_response_id . "'";
        $_response_attrs[] = "data-start_at='" . $_section_response_started_at . "'";
        $_response_attrs[] = "data-response_status='" . $_section_status . "'";
        $_response_attrs[] = "data-content_length='" . $_section_content_length . "'";

        $_response_attrs = implode(" ",$_response_attrs);
        
        // 段用時（秒——生成中計到當下、完成＝凍結；工具列計時器用）
        $_section_elapsed_sec = sc_ai_chats()->message()->view()->get_response_elapsed_sec($response_row);
        
        $content = [];
        $content[] = "<div class='{$_response_cssClass}' {$_response_attrs}>";
        $content[] = "<div class='seg__body'>";
            $content[] = sc_ai_chats()->message()->view()->get_agent_response_thinking_toolbar($_section_elapsed_sec, $_section_response_id);
            $content[] = "<div class='seg__scroll'>";
                $content[] = "<div data-text='thinking' class='seg__text'>" . $_section_content_encoded . "</div>";
            $content[] = "</div>";
        $content[] = "</div>";
        // 水印字（盒中央——純裝飾；pointer 唔食；同摘要同款）
        $content[] = "<div class='seg__mark'><div>thinking</div></div>";
        $content[] = "</div>";
        
        
        return implode("",$content);
    }

}
