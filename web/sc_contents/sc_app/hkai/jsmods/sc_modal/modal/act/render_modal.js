// jsmods/sc_modal/modal/act/render_modal.js —— modal 填槽（act——開單叫工作）
// 職責：render_modal（data-modal reset → header／tabs／body／footer 四槽注入 → footer 空態隱藏 → 綁定事件）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）
// == 2026-09-27 自舊 sc_modal.js 分遷入（one file one action） ==

class _render_modal {
    constructor() {}

    // 填槽＋綁定（建造全權 template 層；本方法負責內容）
    async render_modal(j_modal, modal_config) {
        if (typeof modal_config !== "object")
            return false;

        // data-modal reset（依 config.modal_id——替換式清理 jQuery("[data-modal]") 的依據）
        j_modal.attr("data-modal", modal_config.modal_id);

        // 視窗尺寸變體（config.window_class——如 modal__window--lg；未帶＝基座 960px）
        if (modal_config.window_class) {
            j_modal.find(".modal__window").addClass(modal_config.window_class);
        }

        // 橋接駁（同域 act 橋——內容注入）
        const modal_domain = await registry().sc_modal().modal();
        const fill_modal_content_instance = await modal_domain.act().fill_modal_content();

        fill_modal_content_instance.fill_modal_content(j_modal.find(".modal__header"), modal_config.header)
        fill_modal_content_instance.fill_modal_content(j_modal.find(".modal__tabs"), modal_config.tabs)
        fill_modal_content_instance.fill_modal_content(j_modal.find(".modal__body"), modal_config.body)
        fill_modal_content_instance.fill_modal_content(j_modal.find(".modal__footer"), modal_config.footer)

        // footer 空／未設定／null → 隱藏（just hide——不移除 DOM）
        const has_footer = !!modal_config.footer;
        j_modal.find(".modal__footer").toggle(has_footer);

        // 橋接駁（同域 act 橋——事件綁定）
        const bind_modal_events_instance = await modal_domain.act().bind_modal_events();
        bind_modal_events_instance.bind_modal_events(j_modal);
    }
}

export const render_modal = (function (instance) {
    return function () {

        return instance;
    };
})(new _render_modal());
