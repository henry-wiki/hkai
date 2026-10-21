<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt get_prompt_toolbar_html：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：上工具列（盒上一行——五粒＋右側 agent 選擇）） ==
// == 2026-09-30 建（跟 reference3 prompt-toolbar：附件／加入內容／最頂／最底／靈感問題） ==
// == 2026-10-09 加：右側 agent 選擇（aider／goose／openhands——切現行引擎 daemon_engine） ==

trait get_prompt_toolbar_html
{

    // 上工具列（盒上一行——五粒：附件／加入內容＝coming_soon placeholder；最頂／最底／靈感問題＝真動作）；右側＝agent 選擇（現行引擎 active）
    public function get_prompt_toolbar_html()
    {
        // 標籤（i18n——轉義一次過）
        $label_attach        = sc_html_encode(hkai__i18n()->get("prompt.tool_attach"));
        $label_add_context   = sc_html_encode(hkai__i18n()->get("prompt.tool_add_context"));
        $label_scroll_top    = sc_html_encode(hkai__i18n()->get("prompt.tool_scroll_top"));
        $label_scroll_bottom = sc_html_encode(hkai__i18n()->get("prompt.tool_scroll_bottom"));
        $label_inspiration   = sc_html_encode(hkai__i18n()->get("prompt.tool_inspiration"));

        // agent 選擇來源（引擎清單＋現行引擎——hkai_core 唯一來源）
        $daemon_engines = hkai__core()->get_daemon_engines();
        $daemon_engine  = hkai__core()->get_daemon_engine();
        
        $output_lines = [];
        $output_lines[] = "<div class='sc_composer__prompt_toolbar'>";
            // 附件（placeholder——coming_soon popup；功能鏈未起）
            $output_lines[] = "<button class='sc_composer__tool_button' type='button' data-cmd='coming_soon'><i class='fa-solid fa-paperclip'></i><span>" . $label_attach . "</span></button>";
            // 加入內容（placeholder——coming_soon popup；功能鏈未起）
            $output_lines[] = "<button class='sc_composer__tool_button' type='button' data-cmd='coming_soon'><i class='fa-solid fa-folder'></i><span>" . $label_add_context . "</span></button>";
            // 最頂（捲到頂——message 域 scroll_to_top）
            $output_lines[] = "<button class='sc_composer__tool_button' type='button' data-action='scroll_to_top'><i class='fa-solid fa-arrow-up'></i><span>" . $label_scroll_top . "</span></button>";
            // 最底（捲到底——同浮動回底掣同一 action）
            $output_lines[] = "<button class='sc_composer__tool_button' type='button' data-action='scroll_to_bottom'><i class='fa-solid fa-arrow-down'></i><span>" . $label_scroll_bottom . "</span></button>";
            // 靈感問題（隨機範例問題——填入輸入框；prompt 域 generate_sample）
            $output_lines[] = "<button class='sc_composer__tool_button sc_composer__tool_button--spark' type='button' data-action='generate_sample'><i class='fa-solid fa-wand-magic-sparkles'></i><span>" . $label_inspiration . "</span></button>";
            // agent 選擇（右側——現行引擎；click 即切 daemon_engine）
            $output_lines[] = "<div class='sc_composer__toolbar_right'>";
                $output_lines[] = "<div class='sc_btn_group sc_btn_group--segmented sc_composer__agent_tabs'>";
                // 逐個引擎設定組（aider_daemon／goose_daemon／openhands_api_daemon——顯示名去 _daemon 後綴）
                foreach ($daemon_engines as $daemon_engine_item) {
                    // 顯示名（aider／goose／openhands_api——去 _daemon 後綴）
                    $agent_label = substr($daemon_engine_item, 0, -7);

                    // active 判定（現行引擎對中嗰粒）
                    $agent_active = ($daemon_engine === $daemon_engine_item) ? " sc_btn--active" : "";

                    $btn_attr = [];
                    $btn_attr[] = "data-btn='daemon_engine'";
                    $btn_attr[] = "data-value='{$daemon_engine_item}'";
                    $btn_attr[] = "class='sc_btn{$agent_active}'";
                    $btn_attr[] = "type='button'";

                    $btn_attr = implode(" ", $btn_attr);
                    $output_lines[] = "<button {$btn_attr}>{$agent_label}</button>";
                }
                $output_lines[] = "</div>";
            $output_lines[] = "</div>";
        $output_lines[] = "</div>";
        
        return implode("\r\n", $output_lines);
    }
}
