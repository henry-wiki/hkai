<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message repo_select join_response_answer_contents：repo_select 子域動作（trait——合成入 repo_select_Class；檔＝動作） ==

trait join_response_answer_contents
{
    // 索引制 answer 列（response.{n}.answer）→ 按 index 自然序拼接內容（非索引列略過——回空字串）
    public function join_response_answer_contents($response_rows)
    {
        $indexed_answer_list = [];

        // 逐列解析（response_type＝response.{n}.answer 才收）
        foreach ($response_rows as $response_row_item) {
            $response_type_parts = explode(".", $response_row_item["response_type"]);

            // 判定：唔係三段格式 → 略過
            if (count($response_type_parts) !== 3) {
                continue;
            }
            // 判定：尾段唔係 answer → 略過
            if ($response_type_parts[2] !== "answer") {
                continue;
            }
            // 判定：中段唔係數字 → 略過
            if (!ctype_digit($response_type_parts[1])) {
                continue;
            }

            $indexed_answer_list[] = [
                "index" => (int)$response_type_parts[1],
                "content" => $response_row_item["response_content"],
            ];
        }

        // 排序：index 自然序
        usort($indexed_answer_list, function ($left_item, $right_item) {
            return $left_item["index"] - $right_item["index"];
        });

        $joined_content = "";
        // 逐段拼接
        foreach ($indexed_answer_list as $indexed_answer_item) {
            $joined_content .= $indexed_answer_item["content"];
        }

        return $joined_content;
    }

}
