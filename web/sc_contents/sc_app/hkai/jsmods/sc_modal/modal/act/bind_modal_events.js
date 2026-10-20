// jsmods/sc_modal/modal/act/bind_modal_events.js —— modal 事件綁定（act——開單叫工作）
// 職責：bind_modal_events（close 鈕＋mask 點擊——modal functions 掛在 DOM 元素上，外部可經 element.modal_close 呼叫）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）
// == 2026-09-27 自舊 sc_modal.js 分遷入（one file one action） ==

class _bind_modal_events {
    constructor() {}

    // 綁定 modal 事件（modal functions 掛在 DOM 元素上）
    bind_modal_events(j_modal) {
        const modal_functions = {};
        modal_functions.modal_close = () => {
            // 移除 modal DOM（連同 mask/window）
            j_modal.remove();
        }

        j_modal[0].modal_close = modal_functions.modal_close;

        // close 鈕 → modal_close（委派綁定——涵蓋後注入的 [data-btn=close_modal]；off 先清舊綁定，防重複）
        j_modal.off("click.sc_modal").on("click.sc_modal", ".modal__close,[data-btn=close_modal]", modal_functions.modal_close);

        // mask 點擊 → modal_close
        j_modal.find(".modal__mask").on("click", modal_functions.modal_close);
    }
}

export const bind_modal_events = (function (instance) {
    return function () {

        return instance;
    };
})(new _bind_modal_events());
