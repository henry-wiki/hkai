// jsmods/bind/settings.js —— settings 按鈕綁定（bind 層——一檔一事）
// 職責：settings（[data-btn='modal_open_settings'] 點擊 → 開 settings modal ＋ 渲染選單）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _settings {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派；off 先清舊綁定）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_settings")
            .on("click.sc_ai_settings_settings", "[data-btn='modal_open_settings']", this.settings.bind(this));
    }

    // settings 按鈕點擊（data-tab 指定分頁；未帶 → general（第一頁）→ 開 modal）
    async settings(event) {
        // 動態載入（registry——用先載）

        const j_current = jQuery(event.currentTarget);
        const section = j_current.attr("data-tab") || "general";

        // 判定：callback 由 open_modal 以「modal 例項」為 this 呼叫 → 這裡直接用 modal_module 例項
        // spawn 取組（settings_modal）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        modal_module.open_modal(function (j_modal) {
            modal_module.render_setting_data(j_modal, section);
        });
    }
}

export const settings = (function (instance) {
    return function () {

        return instance;
    };
})(new _settings());
