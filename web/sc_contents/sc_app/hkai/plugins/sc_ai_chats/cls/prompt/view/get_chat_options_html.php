<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt get_chat_options_html：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：區塊格1：chat options 內容（模式鈕＋模型顯示＋hidden）） ==
// == 2026-09-27 自 prompt/view.php 分獨立檔（one file one function） ==

trait get_chat_options_html
{
    // prompt 區塊格1：chat options 內容（模式鈕＋模型選擇顯示＋hidden 欄位——與整頁渲染同一源）
    public function get_chat_options_html($project_slug, $chat_sid, $prompt_draft = null)
    {
        // 活動草稿（prompt_draft——新源優先；無草稿 → 舊存（chats row／SESSION 兜底））
        // 判定：活動有草稿 → 用草稿值
        if ($prompt_draft !== null) {
            $provider_id      = $prompt_draft["provider_id"];
            $model_id         = $prompt_draft["model_id"];
            $reasoning_effort = $prompt_draft["reasoning_effort"];
            $chat_mode        = $prompt_draft["chat_mode"];
        } else {
            // 舊存讀取（chats row → SESSION → 預設）
            $chat_options = sc_ai_chats()->chat_option()->get_chat_options($project_slug, $chat_sid);
            
            $provider_id      = $chat_options["provider_id"];
            $model_id         = $chat_options["model_id"];
            $reasoning_effort = $chat_options["reasoning_effort"];
            $chat_mode        = $chat_options["chat_mode"];
        }
        
        // chat mode（驗證後使用——未選／無效值一律回第一粒顯示模）
        $chat_mode = sc_ai_chats()->chat_option()->get_chat_mode($chat_mode);
        
        
        // model 顯示（provider 或 model 任一為空 → 未選模型）
        $has_model = true;
        // 判定：provider 空 → 未選模型
        if ($provider_id === "") {
            $has_model = false;
        }
        // 判定：model 空 → 未選模型
        if ($model_id === "") {
            $has_model = false;
        }
        
        $model_selector_class = "sc_composer__model_picker";
        // 判定：已選模型 → 加 has_model（CSS 依此切顯隱）
        if ($has_model) {
            $model_selector_class .= " sc_composer__model_picker--has_model";
        }
        
        // 引擎清單（chat_engine——次序＝UI 次序；第一條＝預設）
        $chat_engine = hkai__core()->get_hkai_configs("chat_engine");

        // 現行引擎（runtime 已選值優先）
        $daemon_engine = hkai__core()->get_daemon_engine();

        // 判定：唔喺引擎清單 → 用清單第一條（預設）
        if (!in_array($daemon_engine, $chat_engine)) {
            $daemon_engine = $chat_engine[0];
        }

        // chat mode 掣清單（現行引擎組 chat_mode——key 在＝出嗰粒掣；順序＝寫嘅順序；掣面＝mode 值、hover＝description）
        $daemon_engine_configs = hkai__core()->get_hkai_configs($daemon_engine);
        $chat_mode_config      = $daemon_engine_configs["chat_mode"];

        $output_lines = [];
        // chat mode buttons（sc_btn_group--segmented tab-like 控件）
        $output_lines[] = "<div class='sc_btn_group sc_btn_group--segmented sc_composer__mode_tabs'>";
            // 逐個 UI 模條目（value＝{mode, description}）
            foreach ($chat_mode_config as $chat_mode_name => $chat_mode_item) {
                // 判定：唔係 UI 模條目（無 mode 欄）→ 跳
                if (!isset($chat_mode_item["mode"])) {
                    continue;
                }

                // 掣面字（mode 值）＋ hover（description——先落同名變數）
                $mode        = $chat_mode_item["mode"];
                $description = $chat_mode_item["description"];

                // active 判定（當前 chat_mode 對中嗰粒）
                $chat_mode_active = ($chat_mode === $chat_mode_name) ? " sc_btn--active" : "";

                $btn_attr = [];
                $btn_attr[] = "data-btn='chat_mode'";
                $btn_attr[] = "data-value='{$chat_mode_name}'";
                $btn_attr[] = "title='{$description}'";
                $btn_attr[] = "class='sc_btn{$chat_mode_active}'";

                $btn_attr = implode(" ", $btn_attr);
                $output_lines[] = "<button {$btn_attr}>{$mode}</button>";
            }
        $output_lines[] = "</div>";
        // model selector display
        $output_lines[] = "<div class='" . $model_selector_class . "'>";
        $output_lines[] = "<span class='sc_composer__model_empty'>Please select model</span>";
        $output_lines[] = "<span class='sc_composer__model_provider'>" . $provider_id . "</span>";
        $output_lines[] = "<span class='sc_composer__model_name'>" . $model_id . "</span>";
        $output_lines[] = "<span class='sc_composer__model_effort'>" . $reasoning_effort . "</span>";
        $output_lines[] = "</div>";
        // hidden fields
        $output_lines[] = "<div class='sc_composer__hidden_fields' data-is='model_info' style='display:none;'>";
            $output_lines[] = "<input type='hidden' data-model_field='provider_id' value='" . $provider_id . "' />";
            $output_lines[] = "<input type='hidden' data-model_field='model_id'         value='" . $model_id . "' />";
            $output_lines[] = "<input type='hidden' data-model_field='reasoning_effort' value='" . $reasoning_effort . "' />";
            $output_lines[] = "<input type='hidden' data-model_field='chat_mode'        value='" . $chat_mode . "' />";
        $output_lines[] = "</div>";
        
        return implode("\r\n", $output_lines);
    }
}
