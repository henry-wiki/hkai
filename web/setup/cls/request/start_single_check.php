<?php

namespace sc\setup\request;

// == request start_single_check：start_single_check 動作本體（trait——掛入 request_Class；檔＝動作）
// == 職責：POST formdata[section_id][check_item_code]→ 格單元自己按 code 跑對應檢查（run_check_item——唯一入口）→ 回該卡 HTML（{ok, html:{item}}）

trait start_single_check
{
    // 檢查項更新（檢查卡「更新」——重跑該項檢查、回新卡 HTML）
    public function start_single_check()
    {
        // ── payload：formdata[section_id][check_item_code]（固定兩層——逐層解）

        $posted_formdata = $_POST["formdata"];

        // 解第一層（section）
        $check_item_section_keys = array_keys($posted_formdata);
        $check_item_section      = $check_item_section_keys[0];
        $check_item_section_data = $posted_formdata[$check_item_section];

        // 解第二層（item）
        $check_item_code_keys = array_keys($check_item_section_data);
        $check_item_code      = $check_item_code_keys[0];

        // ── 路由（section＋item → 格單元；格自己按 code 跑——檢查入口＝格單元 run_check_item）
        $sections_instance = setup()->sections();

        $check_item_result = null;

        // 判定：prerequisite 格 → 用嗰格單元（即跑該項）
        if ($check_item_section === "prerequisite") {
            $section_instance  = $sections_instance->prerequisite();
            $check_item_result = $section_instance->run_check_item($check_item_code);
        }
        // 判定：database 格 → 用嗰格單元（即跑該項）
        elseif ($check_item_section === "database") {
            $section_instance  = $sections_instance->database();
            $check_item_result = $section_instance->run_check_item($check_item_code);
        }
        // 判定：settings 格 → 用嗰格單元（即跑該項）
        elseif ($check_item_section === "settings") {
            $section_instance  = $sections_instance->setting();
            $check_item_result = $section_instance->run_check_item($check_item_code);
        }

        // 判定：跑唔到（未知格／項）→ 400
        if ($check_item_result === null) {
            $this->respond_json(["ok" => false, "error" => setup()->i18n()->get("request.error_unknown_item")], 400);
        }

        $check_item_html = setup()->view()->render_setup_check($check_item_result, $check_item_section, $check_item_code);

        $this->respond_json(["ok" => true, "html" => ["item" => $check_item_html]], 200);
    }
}
