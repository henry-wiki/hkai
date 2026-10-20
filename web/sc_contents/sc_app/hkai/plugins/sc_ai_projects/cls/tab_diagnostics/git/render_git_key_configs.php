<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\git;

// == sc_ai_projects tab_diagnostics git render_git_key_configs：tab_diagnostics 域動作（git section；trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（用戶令——重點設定放 config 表上面：重要嘅 key 出 check_item 卡，唔使喺成張表度搵） ==
// == 2026-10-04 擴充（＋提交身分＋行尾；清單驅動——加 key＝加一段） ==

trait render_git_key_configs
{
    // 重點設定檢查卡（清單驅動——加 key＝加一段）
    public function render_git_key_configs($git_configs)
    {
        // i18n 字眼（單一來源——cls/i18n/lang/*.json）
        $longpaths_desc = hkai__i18n()->get("diagnostics.cfg_longpaths_desc");
        $identity_desc  = hkai__i18n()->get("diagnostics.cfg_identity_desc");
        $autocrlf_desc  = hkai__i18n()->get("diagnostics.cfg_autocrlf_desc");

        // 各 key 生效值（後出現者為準——行序＝override 次序；缺席＝null）
        $longpaths_value  = $this->get_git_config_last_value($git_configs, "core.longpaths");
        $user_name_value  = $this->get_git_config_last_value($git_configs, "user.name");
        $user_email_value = $this->get_git_config_last_value($git_configs, "user.email");
        $autocrlf_value   = $this->get_git_config_last_value($git_configs, "core.autocrlf");

        // 檢查項清單（label／desc／passed）
        $check_items = [];
        $check_items[] = [
            "label"  => "core.longpaths",
            "desc"   => $longpaths_desc,
            "passed" => ($longpaths_value === "true"),
        ];
        $check_items[] = [
            "label"  => "user.name / user.email",
            "desc"   => $identity_desc,
            "passed" => ($user_name_value !== null && $user_email_value !== null),
        ];
        $check_items[] = [
            "label"  => "core.autocrlf",
            "desc"   => $autocrlf_desc,
            "passed" => in_array($autocrlf_value, [null, "false"], true),
        ];

        $outputLines = [];

        // 逐項檢查（pass／fail → 樣式與符號）
        foreach ($check_items as $check_item) {
            $check_item_class = "check_item fail";
            $check_item_mark  = "✕";

            // 判定：通過 → pass 樣式＋✓
            if ($check_item["passed"] === true) {
                $check_item_class = "check_item pass";
                $check_item_mark  = "✓";
            }

            $outputLines[] = "<div class='" . $check_item_class . "'>";
                $outputLines[] = "<div class='mark'>" . $check_item_mark . "</div>";
                $outputLines[] = "<div class='cinfo'>";
                    $outputLines[] = "<div class='cname'>" . $check_item["label"] . "</div>";
                    $outputLines[] = "<div class='cdesc'>" . sc_html_encode($check_item["desc"], ENT_QUOTES) . "</div>";
                $outputLines[] = "</div>";
            $outputLines[] = "</div>";
        }

        return implode("\r\n", $outputLines);
    }

    // 取 config 生效值（同名後出現者為準——override 次序；缺席＝null）
    private function get_git_config_last_value($git_configs, $config_key)
    {
        $config_value = null;

        // 逐項掃：同名就記住（後出現者覆蓋）
        foreach ($git_configs as $git_config_item) {
            if ($git_config_item["key"] === $config_key) {
                $config_value = $git_config_item["value"];
            }
        }

        return $config_value;
    }
}
