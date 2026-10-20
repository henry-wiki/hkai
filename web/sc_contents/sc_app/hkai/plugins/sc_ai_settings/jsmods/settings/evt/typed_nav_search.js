// jsmods/bind/typed_nav_search.js —— 選單搜尋過濾綁定（bind 層——一檔一事）
// 職責：typed_nav_search（.modal__search_input 輸入 → keyword 過濾 settings nav 按鈕）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _typed_nav_search {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("input.sc_ai_settings_typed_nav_search")
            .on("input.sc_ai_settings_typed_nav_search", "[data-modal='settings_modal'] .modal__search_input", this.typed_nav_search.bind(this));
    }

    // 動作：搜尋關鍵字 → 過濾選單（動作本體在 nav 層）
    async typed_nav_search(event) {
        // 動態載入（registry——用先載）

        const keyword = jQuery(event.target).val();

        // 橋接駁（同域 act 橋——modal 狀態 j_modal／modal_nav 動作）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        modal_nav_module.filter_sections(modal_module.j_modal, keyword);
    }
}

export const typed_nav_search = (function (instance) {
    return function () {

        return instance;
    };
})(new _typed_nav_search());
