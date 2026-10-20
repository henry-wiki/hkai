<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\action;

// == sc_ai_projects tab_general_form read_input_data：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/handler.php 分遷入（one file one action） ==

trait read_input_data
{
    public function read_input_data()
    {
        $input_data = sc_request()->json();
        
        // 判定：有 serialize 字串 → parse_str 就地解返陣列（一個變數——同參考款）
        if (isset($input_data["serialize"])) {
            $serialize = $input_data["serialize"];
            unset($input_data["serialize"]);
            
            parse_str($serialize, $serialize);
            
            // 結構原樣帶入（formdata 包保持——各層讀自己嗰包，唔攤平）
            $input_data = array_merge($input_data, $serialize);
        }
        
        return $input_data;
    }
}
