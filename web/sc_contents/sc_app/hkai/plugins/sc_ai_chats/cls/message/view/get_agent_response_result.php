<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_response_result：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 message/view.php 分獨立檔（one file one function） ==

trait get_agent_response_result
{
    // 渲染 result 段 HTML（鏡像 thinking 結構：header（結構位·留空）＋body ＞ scroll ＞ seg__text（內容）＋toolbar）
    public function get_agent_response_result($message_row, $response_row)
    {
        $_section_content = trim((string)($response_row["response_content"] ?? ""));
        $_section_content_encoded = sc_html_encode($_section_content);

        // 段身份／狀態／段長／開始時間（落 attribute——前端起 poll 讀返；長度＝mb 字元——同後台裁剪同一把尺）
        $_section_response_id = (string)($response_row["response_id"] ?? "");
        $_section_status = (string)($response_row["response_status"] ?? "");
        $_section_content_length = mb_strlen($_section_content);
        $_section_response_started_at = (string)($response_row["response_started_at"] ?? "");

        // 模型顯示文（message_row——provider/model；同問題列同一口徑）
        $_message_provider_id = (string)($message_row["message_provider_id"] ?? "");
        $_message_model_id    = (string)($message_row["message_model_id"] ?? "");
        $_message_model_text  = trim($_message_provider_id . "/" . $_message_model_id, "/");

        // 段用時（秒——生成中計到當下、完成＝凍結；工具列計時器用）
        $_section_elapsed_sec = sc_ai_chats()->message()->view()->get_response_elapsed_sec($response_row);

        $_response_cssClass = ["seg", "seg--result"];

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
        
        $content = [];
        $content[] = "<div class='{$_response_cssClass}' {$_response_attrs}>";
            // 結構位（鏡像 thinking header——內容待定：現時留空）
            $content[] = "<div class='seg__head'>";
            $content[] = "</div>";
            $content[] = "<div class='seg__body'>";
                $content[] = "<div class='seg__scroll noscroll'>";
                    $content[] = "<div data-text='result' class='seg__text'>" . $_section_content_encoded . "</div>";
                $content[] = "</div>";
                // 動作列（鏡像 thinking／summarize——收歸體入面；顯示規則不變）
                $content[] = sc_ai_chats()->message()->view()->get_agent_response_result_toolbar($_message_model_text, $_section_elapsed_sec, $_section_response_id);
            $content[] = "</div>";
        $content[] = "</div>";
        
        
        return implode("",$content);
    }

}
