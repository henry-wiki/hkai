// jsmods/tab_rule/evt/reset_tab_rule.js —— 重設規則綁定（evt 層——事件入口）
// 職責：reset_tab_rule（[data-btn='reset_tab_rule'] 撳掣 → 兩道確認 → 交 act 層開單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _reset_tab_rule {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_reset_tab_rule")
            .on("click.sc_ai_projects_reset_tab_rule", "[data-btn='reset_tab_rule']", this.reset_tab_rule.bind(this));
    }

    // 重設規則（取 modal → 兩道確認 → 橋接駁 → 交 act 層）
    async reset_tab_rule(event) {
        const j_reset_button = jQuery(event.currentTarget);
        const j_modal = j_reset_button.closest(".modal__overlay");

        // 判定：第一道確認未過 → 不動作（標準確認框——讀 data-confirm_msg）
        const shared = registry().shared();
        if (!await shared.sc_web_utils().is_confirmed(j_reset_button)) {
            return;
        }

        // 判定：第二道確認未過 → 不動作（再三確認——讀 data-confirm_msg_second）
        if (!await shared.sc_web_utils().is_confirmed(j_reset_button, "data-confirm_msg_second")) {
            return;
        }

        // 橋接駁（同域 act 橋——新方式）
        const tab_rule_instance = await registry().sc_ai_projects().tab_rule();
        const reset_tab_rule_instance = await tab_rule_instance.act().reset_tab_rule();

        reset_tab_rule_instance.reset_tab_rule(j_modal, j_reset_button);
    }
}

export const reset_tab_rule = (function (instance) {
    return function () {

        return instance;
    };
})(new _reset_tab_rule());
