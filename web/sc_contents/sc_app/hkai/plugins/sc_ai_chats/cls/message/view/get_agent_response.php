<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_agent_response：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 message/view.php 分獨立檔（one file one function） ==
// == 2026-10-05 改食 DB 列（response_type／response_index 直配——舊扁平列＝0 號索引段、同一條路）

trait get_agent_response
{

    // 渲染回答區段 HTML（摘要＋索引對：response_thinking／response_result ＋ response_index）
    // $include_skeleton：即時訊息 → 有內容嘅段出盒（空段唔預出——等 poll 骨架插入）
    public function get_agent_response($message_row, $message_response_rows, $display_summarize = false, $include_skeleton = false)
    {
        // 摘要兩列（summarize_thinking／summarize_result——收集用；冇列＝空）
        $summarize_thinking_row = [];
        $summarize_result_row   = [];

        $error_message = "";

        // 索引對（index → thinking／result 各自內容＋列）
        $response_pair_map = [];

        // 逐列歸位（type 分流——thinking／result 對入 pair map）
        foreach ($message_response_rows as $message_response_row) {
            $response_type    = (string)($message_response_row["response_type"] ?? "");
            $response_index   = (int)($message_response_row["response_index"] ?? 0);
            $response_content = trim((string)($message_response_row["response_content"] ?? ""));

            // 判定：summarize thinking 列 → 存返（出盒用）
            if ($response_type === "summarize_thinking") {
                $summarize_thinking_row = $message_response_row;
            }

            // 判定：summarize result 列 → 存返（出盒用）
            if ($response_type === "summarize_result") {
                $summarize_result_row = $message_response_row;
            }

            // 判定：error 型 → 失敗原因
            if ($response_type === "error") {
                $error_message = $response_content;
            }

            // 判定：response 兩型 → 索引對歸位（thinking／result 各一邊）
            if (in_array($response_type, ["response_thinking", "response_result"])) {
                $pair_kind = ($response_type === "response_thinking") ? "thinking" : "result";

                $response_pair_map[$response_index][$pair_kind] = [
                    "content" => $response_content,
                    "row"     => $message_response_row,
                ];
            }
        }
        ksort($response_pair_map);

        // 摘要兩格內容（出盒閘——空＝唔出）
        $summarize_thinking_content = trim((string)($summarize_thinking_row["response_content"] ?? ""));
        $summarize_result_content   = trim((string)($summarize_result_row["response_content"] ?? ""));

        $content = [];

        // 判定：即時訊息 → 有內容嘅段出盒（空段唔預出——等 poll 骨架插入）
        if ($include_skeleton === true) {
            // 判定：thinking 有內容 → 出 thinking 盒
            if ($summarize_thinking_content !== "") {
                $content[] = sc_ai_chats()->message()->view()->get_agent_summarize_thinking($message_row, $summarize_thinking_row);
            }

            // 判定：result 有內容 → 出 result 盒
            if ($summarize_result_content !== "") {
                $content[] = sc_ai_chats()->message()->view()->get_agent_summarize_result($message_row, $summarize_result_row);
            }

            // 逐索引對出盒（thinking 先、result 後——同一索引；有內容先出）
            foreach ($response_pair_map as $response_pair_item) {
                $pair_thinking         = (string)($response_pair_item["thinking"]["content"] ?? "");
                $pair_result           = (string)($response_pair_item["result"]["content"] ?? "");
                $response_thinking_row = $response_pair_item["thinking"]["row"] ?? [];
                $response_result_row   = $response_pair_item["result"]["row"] ?? [];

                // 判定：有思考內容 → 出思考段（空＝唔出——等 poll 骨架）
                if ($pair_thinking !== "") {
                    $content[] = sc_ai_chats()->message()->view()->get_agent_response_thinking($message_row, $response_thinking_row);
                }

                // 判定：有答案內容 → 出答案段
                if ($pair_result !== "") {
                    $content[] = sc_ai_chats()->message()->view()->get_agent_response_result($message_row, $response_result_row);
                }
            }

            return implode("", $content);
        }

        // 判定：顯示摘要段（逐 stream 出盒——本型有內容先出；兩支＝兩盒）
        if ($display_summarize) {
            // 判定：thinking 有內容 → 出 thinking 盒
            if ($summarize_thinking_content !== "") {
                $content[] = sc_ai_chats()->message()->view()->get_agent_summarize_thinking($message_row, $summarize_thinking_row);
            }

            // 判定：result 有內容 → 出 result 盒
            if ($summarize_result_content !== "") {
                $content[] = sc_ai_chats()->message()->view()->get_agent_summarize_result($message_row, $summarize_result_row);
            }
        }

        // 逐索引對出盒（thinking 先、result 後——同一索引）
        foreach ($response_pair_map as $response_pair_item) {
            $pair_thinking         = (string)($response_pair_item["thinking"]["content"] ?? "");
            $pair_result           = (string)($response_pair_item["result"]["content"] ?? "");
            $response_thinking_row = $response_pair_item["thinking"]["row"] ?? [];
            $response_result_row   = $response_pair_item["result"]["row"] ?? [];

            // 判定：有思考內容 → 顯示思考段
            if ($pair_thinking !== "") {
                $content[] = sc_ai_chats()->message()->view()->get_agent_response_thinking($message_row, $response_thinking_row);
            }

            // 失敗＝冇答案（error 盒代替）——有失敗 → result 一律唔出
            $display_pair_result = ($pair_result !== "");
            if ($error_message !== "") {
                $display_pair_result = false;
            }

            // 判定：要出 result 段 → 出盒
            if ($display_pair_result) {
                $content[] = sc_ai_chats()->message()->view()->get_agent_response_result($message_row, $response_result_row);
            }
        }

        // 判定：有失敗記錄 → 顯示錯誤段
        if ($error_message !== "") {
            $content[] = "<div class='seg seg--error'>";
                $content[] = "<div class='seg__icon'>⚠️</div>";
                $content[] = "<div class='seg__text' data-text='error'>" . sc_html_encode($error_message) . "</div>";
            $content[] = "</div>";
        }

        return implode("", $content);
    }

}
