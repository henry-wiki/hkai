// jsmods/tab_diagnostics/evt/section_switch.js —— 診斷 section 切換綁定（evt 層——事件入口）
// 職責：section_switch（.diagnostics_pane .settings_nav_button 撳掣 → 交 act 層開單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _section_switch {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_section_switch")
            .on("click.sc_ai_projects_section_switch", ".diagnostics_pane .settings_nav_button[data-section]", this.section_switch.bind(this));
    }

    // 切換診斷 section（取 modal → 橋接駁 → 交 act 層）
    async section_switch(event) {
        const j_button = jQuery(event.currentTarget);
        const j_modal = j_button.closest(".modal__overlay");

        // 判定：modal 未開（搵唔到 modal 元素）→ 唔切
        if (j_modal.length === 0) {
            return;
        }

        // 橋接駁（同域 act 橋——新方式）
        const tab_diagnostics_instance = await registry().sc_ai_projects().tab_diagnostics();
        const section_switch_instance = await tab_diagnostics_instance.act().section_switch();

        section_switch_instance.section_switch(j_modal, j_button);
    }
}

export const section_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _section_switch());
