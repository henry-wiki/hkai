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

        // 判定：下一步 → 先交走本格選擇（唯一遞交路徑）
        if (nav_action === "next") {
            const refresh_setup_process_instance = await setup_instance.act().refresh_setup_process();

            await refresh_setup_process_instance.refresh_setup_process();
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
