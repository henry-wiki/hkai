<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_response_tool：message 域動作（trait——掛入 view_Class；檔＝動作：tool 段） ==
// == 顯示模式跟 result 段（seg__head ＋ scroll ＋ bar）；內容＝本輪工具調用清單（純文字行） ==

trait get_agent_response_tool
{
    // 渲染 tool 段 HTML（跟 result 結構：head／scroll／bar；唔出水印、唔出模型字）
    public function get_agent_response_tool($message_row, $response_row)
    {
        $_section_content = (string)($response_row["response_content"] ?? "");
        $_section_response_id = (string)($response_row["response_id"] ?? "");
        $_section_status = (string)($response_row["response_status"] ?? "");
        $_section_content_length = mb_strlen($_section_content);
        $_section_response_started_at = (string)($response_row["response_started_at"] ?? "");
        $_section_response_finished_at = (string)($response_row["response_finished_at"] ?? "");

        // 內容＝JSON（每條命令一個元素）；舊格式（純文字）→ 當一格
        $_tool_items = json_decode($_section_content, true);

        // 判定：唔係 JSON → 當一格純文字
        if (is_array($_tool_items) === false) {
            $_tool_items = [];

            // 判定：有文字 → 入一格
            if (trim($_section_content) !== "") {
                $_old_item = [];
                $_old_item["seq"]    = 0;
                $_old_item["tool"]   = "";
                $_old_item["status"] = "";
                $_old_item["input"]  = trim($_section_content);
                $_old_item["output"] = "";

                $_tool_items[] = $_old_item;
            }
        }

        // 工具段用時（最早開始 → 最遲完成；缺＝0）
        $_section_elapsed_sec = 0;

        // 判定：起止齊 → 計秒數
        if ($_section_response_started_at !== "" && $_section_response_finished_at !== "") {
            $_section_elapsed_sec = max(0, strtotime($_section_response_finished_at) - strtotime($_section_response_started_at));
        }

        $_section_elapsed_minutes = floor($_section_elapsed_sec / 60);
        $_section_elapsed_seconds = $_section_elapsed_sec % 60;
        $_section_elapsed_text    = $_section_elapsed_minutes . ":" . str_pad((string)$_section_elapsed_seconds, 2, "0", STR_PAD_LEFT);

        $_response_cssClass = ["seg", "seg--tool"];

        // 判定：冇任何命令 → 收起
        if (count($_tool_items) < 1) {
            $_response_cssClass[] = "hidden";
        }

        $_response_cssClass = implode(" ", $_response_cssClass);

        $_response_attrs = [];
        $_response_attrs[] = "data-response_id='" . $_section_response_id . "'";
        $_response_attrs[] = "data-start_at='" . $_section_response_started_at . "'";
        $_response_attrs[] = "data-response_status='" . $_section_status . "'";
        $_response_attrs[] = "data-content_length='" . $_section_content_length . "'";

        $_response_attrs = implode(" ", $_response_attrs);

        $content = [];
        $content[] = "<div class='{$_response_cssClass}' {$_response_attrs}>";
        $content[] = "<div class='seg__head'></div>";
        $content[] = "<div class='seg__body'>";
        $content[] = "<div class='seg__scroll noscroll'>";
        $content[] = "<div data-text='tool' class='seg__text seg__text--tool'>";

        // 逐條命令：一格
        foreach ($_tool_items as $_tool_item) {
            $_tool_name   = (string)($_tool_item["tool"] ?? "");
            $_tool_status = (string)($_tool_item["status"] ?? "");
            $_tool_input  = trim((string)($_tool_item["input"] ?? ""));
            $_tool_output = trim((string)($_tool_item["output"] ?? ""));
            $_tool_seq    = (int)($_tool_item["seq"] ?? 0);

            $_tool_input_encoded  = sc_html_encode($_tool_input);
            $_tool_output_encoded = sc_html_encode($_tool_output);

            $content[] = "<div class='tool_item'>";
            $content[] = "<div class='tool_item__head'>";
            $content[] = "<span class='tool_item__seq'>#" . $_tool_seq . "</span>";
            $content[] = "<span class='tool_item__name'>" . sc_html_encode($_tool_name) . "</span>";
            $content[] = "<span class='tool_item__status'>" . sc_html_encode($_tool_status) . "</span>";
            $content[] = "</div>";

            // 判定：有命令原文 → 出主體
            if ($_tool_input !== "") {
                $_tool_command = $_tool_input;

            // 判定：包住 {'command': '...'} → 取命令本體（引號原樣保留——可直接貼去 terminal）
            if (preg_match("/^\{\s*'command'\s*:\s*'(.*)'\s*\}$/s", trim($_tool_input), $_tool_match) === 1) {
                $_tool_command = $_tool_match[1];
            }

            $_tool_command_encoded = sc_html_encode($_tool_command);

            $content[] = "<div class='tool_item__line'>";
            $content[] = "<pre class='tool_item__input'>" . $_tool_command_encoded . "</pre>";
            $content[] = "<button type='button' class='seg__action tool_item__copy' data-action='message_copy' data-copy_scope='.tool_item' data-copy_target='.tool_item__input' title='" . hkai()->i18n()->get("answer.copy") . "'>";
            $content[] = "<i class='fa-regular fa-copy'></i>";
            $content[] = "</button>";
            $content[] = "</div>";
            }

            // 判定：有輸出 → 出輸出塊
            if ($_tool_output !== "") {
                $content[] = "<pre class='tool_item__output'>" . $_tool_output_encoded . "</pre>";
            }

            $content[] = "</div>";
        }

        $content[] = "</div>";
        $content[] = "</div>";
        $content[] = "<div class='seg__bar'>";
        $content[] = "<div class='seg__bar_actions'></div>";
        $content[] = "<span class='response_elapsed' data-response_id='{$_section_response_id}' data-elapsed_sec='{$_section_elapsed_sec}' data-elapsed_ts='0'>{$_section_elapsed_text}</span>";
        $content[] = "</div>";
        $content[] = "</div>";
        $content[] = "</div>";

        return implode("", $content);
    }
}
