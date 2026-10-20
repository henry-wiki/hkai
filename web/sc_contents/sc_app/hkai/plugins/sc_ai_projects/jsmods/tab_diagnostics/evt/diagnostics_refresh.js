// jsmods/tab_diagnostics/evt/diagnostics_refresh.js —— 診斷 pane 重新整理綁定（evt 層——事件入口）
// 職責：diagnostics_refresh（[data-btn='diagnostics_refresh'] 撳掣 → 當前 section 再切一次＝自己載返自己）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _diagnostics_refresh {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_diagnostics_refresh")
            .on("click.sc_ai_projects_diagnostics_refresh", "[data-btn='diagnostics_refresh']", this.diagnostics_refresh.bind(this));
    }

    // 重載當前 section（撳 active 嗰粒——重行走同一條切換載入路；pane 未開＝觸發空集合、天然唔做）
    diagnostics_refresh(event) {
        const j_refresh_button = jQuery(event.currentTarget);
        const j_pane = j_refresh_button.closest(".diagnostics_pane");

        j_pane.find(".settings_nav_button.active").trigger("click");
    }
}

export const diagnostics_refresh = (function (instance) {
    return function () {

        return instance;
    };
})(new _diagnostics_refresh());
