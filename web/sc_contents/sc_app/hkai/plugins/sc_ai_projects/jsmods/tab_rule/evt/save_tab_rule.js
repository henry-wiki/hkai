// jsmods/tab_rule/evt/save_tab_rule.js —— 儲存規則綁定（evt 層——事件入口）
// 職責：save_tab_rule（[data-btn='save_tab_rule'] 撳掣 → 交 act 層開單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _save_tab_rule {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_save_tab_rule")
            .on("click.sc_ai_projects_save_tab_rule", "[data-btn='save_tab_rule']", this.save_tab_rule.bind(this));
    }

    // 儲存規則（取 modal → 橋接駁 → 交 act 層）
    async save_tab_rule(event) {
        const j_save_button = jQuery(event.currentTarget);
        const j_modal = j_save_button.closest(".modal__overlay");

        // 判定：使用者未確認 → 不動作（標準確認框——讀 data-confirm_msg）
        const shared = registry().shared();
        if (!await shared.sc_web_utils().is_confirmed(j_save_button)) {
            return;
        }

        // 橋接駁（同域 act 橋——新方式）
        const tab_rule_instance = await registry().sc_ai_projects().tab_rule();
        const save_tab_rule_instance = await tab_rule_instance.act().save_tab_rule();

        save_tab_rule_instance.save_tab_rule(j_modal, j_save_button);
    }
}

export const save_tab_rule = (function (instance) {
    return function () {

        return instance;
    };
})(new _save_tab_rule());
