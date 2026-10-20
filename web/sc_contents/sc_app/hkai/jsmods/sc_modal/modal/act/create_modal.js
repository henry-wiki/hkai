// jsmods/sc_modal/modal/act/create_modal.js —— 建立 modal（act——開單叫工作）
// 職責：create_modal（替換式——清掉頁面上既有 framework modal 再重建；建造全權 template 層；本檔只做 append + 交 render 填槽）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）
// == 2026-09-27 自舊 sc_modal.js 分遷入（one file one action） ==

class _create_modal {
    constructor() {}

    // 建立 modal（回傳 j_modal DOM 元素——.modal__overlay）
    async create_modal(modal_config) {
        const j_body = jQuery("body");

        // 替換式建立（清掉頁面上既有 framework modal）
        jQuery("[data-modal]").remove();

        // 橋接駁（同域 act 橋——DOM 骨架）
        const modal_domain = await registry().sc_modal().modal();
        const get_modal_template_instance = await modal_domain.act().get_modal_template();

        // 建造（template 層——結構）＋ append 到 body
        const j_new_modal = get_modal_template_instance.get_modal_template();
        j_new_modal.appendTo(j_body);

        // 橋接駁（同域 act 橋——填槽＋綁定）
        const render_modal_instance = await modal_domain.act().render_modal();
        await render_modal_instance.render_modal(j_new_modal, modal_config);

        return j_new_modal;
    }
}

export const create_modal = (function (instance) {
    return function () {

        return instance;
    };
})(new _create_modal());
