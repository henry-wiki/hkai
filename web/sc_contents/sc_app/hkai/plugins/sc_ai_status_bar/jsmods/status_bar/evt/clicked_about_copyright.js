// jsmods/status_bar/evt/clicked_about_copyright.js —— 版權 item 點擊綁定（evt 層——一事件一檔）
// 職責：clicked_about_copyright（[data-click='about_copyright'] 點擊 → 開 Settings modal 直停「關於 HKAI」分頁）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _clicked_about_copyright {
    constructor() {}

    // 綁定註冊（由 status_bar.evt().init() 召集——一次；selector 委派；off 先清舊綁定）
    init() {
        jQuery(document)
            .off("click.sc_ai_status_bar_clicked_about_copyright")
            .on("click.sc_ai_status_bar_clicked_about_copyright", "[data-click='about_copyright']", this.clicked_about_copyright.bind(this));
    }

    // 點擊分派（開 settings modal → 渲染選單＋直接顯示 about 分頁——照 settings.js evt 既有 flow，不重寫）
    async clicked_about_copyright() {
        // 動態載入（registry——用先載；spawn 取組 settings_modal）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const settings_modal_instance = await modal_domain.act().settings_modal();

        // 開殼後回呼：渲染選單＋停在 about 分頁
        settings_modal_instance.open_modal(function (j_modal) {
            settings_modal_instance.render_setting_data(j_modal, "about");
        });
    }
}

export const clicked_about_copyright = (function (instance) {
    return function () {

        return instance;
    };
})(new _clicked_about_copyright());
