<?php

namespace sc\setup\request;

// == request start_single_install：start_single_install 動作本體（trait——掛入 request_Class；檔＝動作）
// == 職責：POST formdata[section_id][check_item_code]→ 格單元自己跑該項安裝（run_unit_install——唯一入口）→ 記結果（set_unit_install_result）→ 回該行 HTML（{ok, html:{item}, install_ok}）；清單全部跑完且全成功＝收尾改名 setup_done.json

trait start_single_install
{
    // 單項安裝（逐項一項一項——回該行 HTML；全清單完成＝收尾）
    public function start_single_install()
    {
        // ── payload：formdata[section_id][check_item_code]（固定兩層——逐層解）

        $posted_formdata = $_POST["formdata"];

        // 解第一層（section）
        $install_section_keys = array_keys($posted_formdata);
        $install_section_id   = $install_section_keys[0];
        $install_section_data = $posted_formdata[$install_section_id];

        // 解第二層（item）
        $check_item_code_keys = array_keys($install_section_data);
        $check_item_code      = $check_item_code_keys[0];

        // ── 執行（格＋項名 → 單元；單元自己跑 setup_install——標記＝專屬動作）
        $sections_instance = setup()->sections();
        $install_section   = $sections_instance->install();

        $unit_instance = $sections_instance->get_section_unit_instance($install_section_id, $check_item_code);

        // 判定：搵唔到單元（未知格／項——用戶輸入）→ 400
        if ($unit_instance === null) {
            $this->respond_json(["ok" => false, "error" => setup()->i18n()->get("request.error_unknown_item")], 400);
        }

        $install_step_result = $install_section->run_unit_install($install_section_id, $check_item_code);

        // 過程檔：記安裝結果（sections.install.<格>.<項名>——每項真實結果）
        $install_section->set_unit_install_result($install_section_id, $check_item_code, $install_step_result);

        // ── 收尾判定（全清單跑完且全成功 → 改名 setup_done.json）
        $install_all_done = true;

        $install_list = $install_section->get_install_list();

        // 逐行（有行未跑／有行失敗 → 未完成）
        foreach ($install_list["steps"] as $install_list_step_item) {
            $install_list_step_result = $install_list_step_item["result"];

            // 判定：未跑（冇結果）→ 未完成
            if ($install_list_step_result === null) {
                $install_all_done = false;
            }

            // 判定：有結果但失敗 → 未完成
            elseif ($install_list_step_result["ok"] !== true) {
                $install_all_done = false;
            }
        }

        $install_footer_html = null;

        // 判定：全部跑完且全成功 → 收尾（過程檔加完成時間後改名）＋底列重報（安裝掣轉「完成」掣）
        if ($install_all_done === true) {
            $install_section->finish_setup_process();

            $install_section_nav  = $sections_instance->get_section_nav("install");
            $install_footer_lines = $install_section->build_footer($install_section_nav);

            $install_footer_html = implode("\r\n", $install_footer_lines);
        }

        // ── 回該行 HTML（行＝本項——狀態由記錄重報）
        $install_steps = $install_section->get_install_steps();
        $install_step  = null;

        // 逐行（格＋項名＝身分——搵返本項）
        foreach ($install_steps as $install_step_item) {
            // 判定：格同名 →
            if ($install_step_item["section_id"] === $install_section_id) {
                // 判定：項名同名 → 鎖定本項
                if ($install_step_item["check_item_code"] === $check_item_code) {
                    $install_step = $install_step_item;
                }
            }
        }

        $step_state = $install_section->get_install_step_state($install_section_id, $check_item_code);

        $check_item_html = $install_section->render_install_row($install_step, $step_state);

        $response_html = ["item" => $check_item_html];

        // 判定：本次收尾 → 底列一併帶返（前端換走安裝掣）
        if ($install_footer_html !== null) {
            $response_html["footer"] = $install_footer_html;
        }

        $this->respond_json([
            "ok"         => true,
            "html"       => $response_html,
            "install_ok" => ($install_step_result["ok"] === true),
        ], 200);
    }
}
