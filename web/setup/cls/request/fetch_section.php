<?php

namespace sc\setup\request;

// == request fetch_section：fetch_section 動作本體（trait——掛入 request_Class；檔＝動作）
// == 職責：POST section=<id> → 即時重出整件（{ok, html:{left, right}}——左選單＋右欄）

trait fetch_section
{
    // 逐格內容（切格即時重出整件——回 JSON；前端 setup 域唯一切格 fetch 對象）
    public function fetch_section()
    {
        // ── payload：section（邊格——同 nav 家族同 key；裁決交 sections 域）
        $section_id = setup()->sections()->get_section_id($_POST["section"]);

        // 即時重出整件（左選單＋右欄一件帶返——同 switch_language 同形）
        $sections = setup()->sections()->get_sections();

        $result = [];

        $result["ok"]   = true;

        $modal_left_lines  = setup()->view()->build_modal_left($sections, $section_id);
        $modal_right_lines = setup()->view()->build_modal_right($sections, $section_id);

        $result["html"] = [
            "left"  => implode("\r\n", $modal_left_lines),
            "right" => implode("\r\n", $modal_right_lines),
        ];

        $this->respond_json($result, 200);
    }
}
