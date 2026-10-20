// jsmods/bind/nav_section.js —— 選單切頁綁定（bind 層——一檔一事）
// 職責：nav_section（.settings_nav_button 點擊 → 切換 section）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _nav_section {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_nav_section")
            .on("click.sc_ai_settings_nav_section", "[data-modal='settings_modal'] .settings_nav_button", this.nav_section.bind(this));
    }

    // 動作：切換 section（動作本體在 nav 層）
    async nav_section(event) {
        // 動態載入（registry——用先載）

        // spawn 取組（modal_nav）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        modal_nav_module.switch_section(jQuery(event.currentTarget));
    }
}

export const nav_section = (function (instance) {
    return function () {

        return instance;
    };
})(new _nav_section());
