// jsmods/general/evt/general_save.js —— General 儲存鈕綁定（evt 層——一檔一事）
// 職責：general_save（[data-btn='general_save'] 點擊 → 儲存 General 偏好（需 modal 根——表單欄位在 modal 內；儲存鈕傳入＝載入狀態用））
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _general_save {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_general_save")
            .on("click.sc_ai_settings_general_save", "[data-modal='settings_modal'] [data-btn='general_save']", this.general_save.bind(this));
    }

    // 動作：儲存 General 偏好（modal 根經 modal 狀態取；儲存鈕＝載入狀態用）
    async general_save(event) {
        const j_save_button = jQuery(event.currentTarget);

        // spawn 取組（modal 狀態 j_modal／general 分頁）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const general_domain = await (await registry().sc_ai_settings()).general();
        const general_module = await general_domain.act().general();

        general_module.settings_save_general(j_save_button, modal_module.j_modal);
    }
}

export const general_save = (function (instance) {
    return function () {

        return instance;
    };
})(new _general_save());
