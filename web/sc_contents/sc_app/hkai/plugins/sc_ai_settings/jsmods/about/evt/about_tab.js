// jsmods/about/evt/about_tab.js —— About 內層 tab 切換綁定（evt 層——一檔一事）
// 職責：about_tab（.sc_about__tab 點擊 → 按鈕 active＋對應面板顯示；純前端，不重載後端）
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _about_tab {
    constructor() {}

    // 綁定註冊（由 about 域 init 召集——一次；selector 委派——[data-modal='settings_modal'] 範圍）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_about_tab")
            .on("click.sc_ai_settings_about_tab", "[data-modal='settings_modal'] .sc_about__tab", this.about_tab.bind(this));
    }

    // 動作：切換 tab（同一個 .sc_about 範圍內——按鈕 active／面板 hidden 互斥）
    about_tab(event) {
        const j_tab   = jQuery(event.currentTarget);
        const tab_key = j_tab.attr("data-about_tab");
        const j_about = j_tab.closest(".sc_about");

        j_about.find(".sc_about__tab").removeClass("active");
        j_tab.addClass("active");

        j_about.find(".sc_about__panel").attr("hidden", "hidden");

        const j_target_panel = j_about.find(".sc_about__panel[data-about_panel='" + tab_key + "']");
        j_target_panel.removeAttr("hidden");
    }
}

export const about_tab = (function (instance) {
    return function () {

        return instance;
    };
})(new _about_tab());
