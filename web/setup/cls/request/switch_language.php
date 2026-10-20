<?php

namespace sc\setup\request;

// == request switch_language：switch_language 動作本體（trait——掛入 request_Class；檔＝動作）
// == 職責：POST formdata[welcome][language][value]＋section=<id> → 即時記入過程檔＋即時以新語言重出整件（{ok, html:{left, right}}）

trait switch_language
{
    // 轉語言（語言選擇——即時記錄＋即時以新語言重出整件；唔改 URL·唔 reload）
    public function switch_language()
    {
        // 收語言值（formdata[welcome][language][value]——同 welcome 格 radio 同名）
        $posted_language = $_POST["formdata"]["welcome"]["language"]["value"];

        // 收格 id（section——重出邊格；同 fetch_section 同 key；裁決交 sections 域）
        $section_id = setup()->sections()->get_section_id($_POST["section"]);

        // 即時記入過程檔（welcome.language——用戶選擇；完成檔已存在＝記錄方法自動唔寫）
        setup()->state()->set_setting_setup_process("welcome", "language", $posted_language);

        // 以新語言重出整件（語言讀過程檔——啱啱寫入嗰個）（左選單＋右欄——同 fetch_section 同形）
        $sections = setup()->sections()->get_sections();

        $result = [];

        $result["ok"]   = true;

        $modal_left_lines  = setup()->view()->build_modal_left($sections, $section_id);
        $modal_right_lines = setup()->view()->build_modal_right($sections, $section_id);

        $result["html"] = [
            "left"  => implode("\r\n", $modal_left_lines),
            "right" => implode("\r\n", $modal_right_lines),
        ];

        // 頁面標題（跟新語言——前端直接寫 document.title）
        $result["title"] = setup()->i18n()->get("page.title");

        $this->respond_json($result, 200);
    }
}
