// jsmods/setup/evt/switch_section.js —— 切格綁定（evt 層——一檔一事）
// 職責：switch_section（footer 掣＝data-setup_nav〔目標〕＋data-nav_action〔refresh／prev／next〕；下一步＝先交後跳；左選單唔在此列）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _switch_section {
    constructor() {}

    // 綁定註冊（域召集呼叫——錨 modal 根；頁面初始化一次）
    init() {
        const j_modal = setup_instance.get_modal_element();

        j_modal.off("click.setup_switch_section").on("click.setup_switch_section", this.on_click.bind(this));
    }

    // 切格點擊（footer 掣——按鈕自報：去邊＋做咩）
    async on_click(event) {
        const j_nav_element = jQuery(event.target).closest("[data-nav_action]");

        // 判定：唔係導航掣 → 唔動
        if (j_nav_element.length === 0) {
            return;
        }

        const nav_section_id = j_nav_element.attr("data-setup_nav");
        const nav_action = j_nav_element.attr("data-nav_action");

        // 判定：下一步 → 後台儲存＋驗證（後台唯一裁決；唔通過即停）
        if (nav_action === "next") {
            const current_section_id = setup_instance.get_current_section_id();

            const refresh_setup_process_instance = await setup_instance.act().refresh_setup_process();
            const field_pairs = refresh_setup_process_instance.collect_current_fields();

            const validate_section_instance = await setup_instance.req().validate_section();
            const validate_result = await validate_section_instance.validate_section(current_section_id, field_pairs);

            // 判定：網絡失敗 → 唔跳
            if (validate_result === null) {
                return;
            }

            // 判定：驗證唔通過 → 顯示錯誤＋唔跳
            if (validate_result.ok !== true) {
                const database_source_mode_instance = await setup_instance.evt().database_source_mode();

                database_source_mode_instance.render_status("fail", validate_result.error || "");

                return;
            }
        }

        // spawn 取 act 組（switch_section——切格）
        const switch_section_instance = await setup_instance.act().switch_section();

        await switch_section_instance.switch_section(nav_section_id);
    }
}

export const switch_section = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_section());
