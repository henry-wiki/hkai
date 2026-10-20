// jsmods/sc_modal/modal/act/get_modal_template.js —— modal DOM 骨架建造（act——開單叫工作）
// 職責：get_modal_template（bare skeleton——只建結構不填內容；內容由 render_modal 填）
// 建造約定：appendTo 鏈式建造；foot 恆建（modal.css 可後續處理空態）；data-modal 預設空字串（render_modal 依 config.modal_id reset）
// 樣式：一律用 modal.css 通用基座（.modal__overlay > .modal__mask + .modal__window > .modal__header / .modal__tabs / .modal__body / .modal__footer）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）
// == 2026-09-27 自舊 sc_modal_template.js 併入（舊 export default＋parent 注入 → 新形；parent 從未被使用＝零行為影響） ==

class _get_modal_template {
    constructor() {}

    // default 佈局 builder（基座結構——appendTo 鏈式建造）
    get_modal_template() {
        const j_modal           = jQuery("<div class='modal__overlay'></div>");
        const j_modal_mask    = jQuery("<div class='modal__mask'></div>").appendTo(j_modal);
        const j_modal_window  = jQuery("<div class='modal__window'></div>").appendTo(j_modal);
        const j_modal_head    = jQuery("<div class='modal__header'></div>").appendTo(j_modal_window);
        const j_modal_tabs    = jQuery("<div class='modal__tabs'></div>").appendTo(j_modal_window);
        const j_modal_body    = jQuery("<div class='modal__body'></div>").appendTo(j_modal_window);
        const j_modal_foot    = jQuery("<div class='modal__footer'></div>").appendTo(j_modal_window);

        // data-modal 預設空字串（render_modal 依 config.modal_id reset——替換式清理依據）
        j_modal.attr("data-modal", "");

        const j_modal_head_content = jQuery("<div class='modal__header-content'></div>").appendTo(j_modal_head);
        const j_modal_title = jQuery("<div class='modal__title'></div>").appendTo(j_modal_head_content);
        const j_modal_subtitle = jQuery("<div class='modal__subtitle'></div>").appendTo(j_modal_head_content);
        const j_modal_close = jQuery("<button class='modal__close' title='關閉'>✕</button>").appendTo(j_modal_head);

        return j_modal;
    }
}

export const get_modal_template = (function (instance) {
    return function () {

        return instance;
    };
})(new _get_modal_template());
