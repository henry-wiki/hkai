// jsmods/tab_environment/evt/update_tab_environment.js —— 更新環境綁定（evt 層——事件入口）
// 職責：update_tab_environment（[data-btn='update_tab_environment'] 撳掣 → 交 act 層開單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _update_tab_environment {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_update_tab_environment")
            .on("click.sc_ai_projects_update_tab_environment", "[data-btn='update_tab_environment']", this.update_tab_environment.bind(this));
    }

    // 更新環境（取 modal → 橋接駁 → 交 act 層——內容重生唔傷資料、免確認）
    async update_tab_environment(event) {
        const j_update_button = jQuery(event.currentTarget);
        const j_modal = j_update_button.closest(".modal__overlay");

        // 橋接駁（同域 act 橋——新方式）
        const tab_environment_instance = await registry().sc_ai_projects().tab_environment();
        const update_tab_environment_instance = await tab_environment_instance.act().update_tab_environment();

        update_tab_environment_instance.update_tab_environment(j_modal, j_update_button);
    }
}

export const update_tab_environment = (function (instance) {
    return function () {

        return instance;
    };
})(new _update_tab_environment());
