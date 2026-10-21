<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_chat_message_slot：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 message/view.php 分獨立檔（one file one function） ==

trait get_chat_message_slot
{

    // 渲染單一問題列（統一結構：template ＋ slots——前端 insert template、灌 slot；同 Process Viewer）
    // template：固定外殼（data attrs ＋ 資料容器 ＋ 骨架 ＋ 活動日誌區——動態內容位置留空 slot 佔位）
    // slots：動態內容（user_message＝問題、agent_message＝回答——各帶自己嘅 data-slot 容器）
    // $message_row 固定為 user 問題列；$message_response_rows 有值 = 歷史已回答 → 回答渲染進 agent_message
    public function get_chat_message_slot(
        $message_row = [], 
        $message_response_rows = [], 
        $display_summarize = false, 
        $show_log = true
    )
    {
        $message_row = sc_filter_emptys($message_row);
        
        // 即時訊息判定（非終態＝後端算出嘅「工作中」；即時 render 同批載兩路一視同仁）
        // 前端據 data-working 判有冇進行中——JS 零 status 清單（詞彙源頭留後端）
        $message_status  = (string)($message_row["message_status"] ?? "");

        // 模型顯示文（provider/model——兩者皆空＝空；問題列帶落兩邊氣泡）
        $message_provider_id = (isset($message_row["message_provider_id"]) ? $message_row["message_provider_id"] : null);
        $message_model_id    = (isset($message_row["message_model_id"]) ? $message_row["message_model_id"] : null);
        $message_model_text  = trim($message_provider_id . "/" . $message_model_id, "/");
        $is_live_message = $show_log === true || !sc_ai_chats()->fsm()->is_terminal_status($message_status);
        $working_value   = $is_live_message === true ? "1" : "0";

        $message_wrapper_attrs = [];
        $message_wrapper_attrs[] = "data-message_id   ='". (isset($message_row["message_id"]) ? $message_row["message_id"] : null) ."'";
        $message_wrapper_attrs[] = "data-start_at     ='" . (isset($message_row["message_created_at"]) ? $message_row["message_created_at"] : null) . "'";
        $message_wrapper_attrs[] = "data-status       ='" . (isset($message_row["message_status"]) ? $message_row["message_status"] : null) . "'";
        $message_wrapper_attrs[] = "data-working      ='" . $working_value . "'";
        $message_wrapper_attrs[] = "data-render_status='frame'";

        $message_wrapper_attrs = implode(" ",$message_wrapper_attrs);
        
        // template：外殼（message_wrapper attrs ＋ 資料容器 ＋ 骨架 ＋ 活動日誌區）
        $template = [];
        $template[] = "<div class='message_wrapper' ".$message_wrapper_attrs.">";
        // 資料容器（隱藏——未來數據／互動信息統一放呢度；狀態軸維持 wrapper attribute）
        $template[] = "<div class='message_data' hidden></div>";
        $template[] = "<div class='user_message' data-slot='user_message'></div>";
        $template[] = "<div class='agent_message' data-slot='agent_message'></div>";
        
        // 活動日誌區（只限即時訊息——預設載入畫面唔顯示；完成後仍保留）
        if ($is_live_message === true) {
            $message_chat_id     = (int)($message_row["chat_id"] ?? 0);
            $message_log_entries = sc_ai_chats()->poll()->log()->parse_activity_events($message_chat_id, (int)($message_row["message_id"] ?? 0));

            $template[] = "<div class='log_message'>";
                $template[] = "<div class='activity_monitor' data-log_index='" . count($message_log_entries) . "'>";
                    $template[] = "<div class='activity_state_icon'><span class='loading-dots'></span></div>";
                    $template[] = "<div class='activity_lines'>";
                        // 最新一條活動記錄（uid＝md5(time+event+status+message)——前端據此判「已存在」）
                        $log_entry_item = [];

                        // 判定：有記錄 → 取最後一條
                        if (count($message_log_entries) > 0) {
                            $log_entry_item = $message_log_entries[count($message_log_entries) - 1];
                        }

                        $log_entry_level   = strtolower((string)($log_entry_item["level"] ?? "info"));
                        $log_entry_message = (string)($log_entry_item["message"] ?? "");
                        $log_entry_uid     = (string)($log_entry_item["activity_uid"] ?? "");
                        $log_entry_event   = (string)($log_entry_item["event"] ?? "");

                        $log_line_attrs  = "data-activity_uid='" . $log_entry_uid . "'";
                        $log_line_attrs .= " data-log_event='" . sc_html_encode($log_entry_event) . "'";
                        $log_line_attrs .= " data-log_status='" . sc_html_encode($log_entry_level) . "'";

                        $template[] = "<div class='activity_line activity_level_" . $log_entry_level . "' " . $log_line_attrs . ">";
                            $template[] = sc_html_encode($log_entry_message);
                        $template[] = "</div>";
                    $template[] = "</div>";
                $template[] = "</div>";
            $template[] = "</div>";
        }

        // 總用時列（回答底——左右分隔線＋中央用時；caption 靜態、時間動態——tick 只更新 .total_elapsed_time；常駐跟隨訊息）
        $total_elapsed_caption = sc_html_encode(hkai__i18n()->get("message.total_elapsed"));
        // 判定：即時訊息 → 以現時起算（poll 每輪更新、前端每秒遞增）
        if ($is_live_message === true) {
            $message_elapsed_sec  = max(0, time() - (int)strtotime((string)(isset($message_row["message_created_at"]) ? $message_row["message_created_at"] : null)));
            $message_elapsed_text = sc_ai_chats()->message()->view()->format_elapsed_sec($message_elapsed_sec);

            $template[] = "<div class='total_elapsed' data-elapsed_sec='{$message_elapsed_sec}' data-elapsed_ts='0'>";
                $template[] = "<span class='total_elapsed_caption'>{$total_elapsed_caption}</span>";
                $template[] = "<span class='total_elapsed_time'>{$message_elapsed_text}</span>";
            $template[] = "</div>";
        // 判定：已完結 → 讀後台記落嘅總用時（靜態直接輸出；冇記錄＝唔出——不示假值）
        } else {
            $message_elapsed_sec = max(0, (int)(isset($message_row["message_elapsed_sec"]) ? $message_row["message_elapsed_sec"] : null));

            // 判定：有總用時記錄 → 出列
            if ($message_elapsed_sec > 0) {
                $message_elapsed_text = sc_ai_chats()->message()->view()->format_elapsed_sec($message_elapsed_sec);

                $template[] = "<div class='total_elapsed' data-elapsed_sec='{$message_elapsed_sec}' data-elapsed_ts='0'>";
                    $template[] = "<span class='total_elapsed_caption'>{$total_elapsed_caption}</span>";
                    $template[] = "<span class='total_elapsed_time'>{$message_elapsed_text}</span>";
                $template[] = "</div>";
            }
        }

        $template[] = "</div>";
        
        // 判定：即時訊息 → agent_message 出齊段骨架（後端砌——前端只寫入）
        $include_skeleton = $is_live_message;
        
        // slots：動態內容（各帶自己嘅容器——前端 replaceWith 後下一輪仍可直接覆蓋）
        $slots = [];
        $slots["user_message"]  = "<div class='user_message' data-slot='user_message'>" . sc_ai_chats()->message()->view()->get_user_prompt($message_row, $message_model_text) . "</div>";
        $slots["agent_message"] = "<div class='agent_message' data-slot='agent_message'>" . sc_ai_chats()->message()->view()->get_agent_response($message_row, $message_response_rows, $display_summarize, $include_skeleton) . "</div>";
        
        $message_render = [];
        $message_render["template"] = [];
        $message_render["template"]["message"] = implode("", $template);
        $message_render["slots"] = $slots;
        
        return $message_render;
    }

}
