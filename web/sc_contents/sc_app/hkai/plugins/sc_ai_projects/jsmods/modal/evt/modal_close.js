// jsmods/modal/evt/modal_close.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（modal_close——取消（footer））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _modal_close {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 取消（footer）
        jQuery(document)
            .off("click.sc_ai_projects_cancel")
            .on("click.sc_ai_projects_cancel", "[data-btn='project_cancel']", this.modal_close.bind(this));
    }

    // 取消（footer 取消鈕——關閉 modal）
    modal_close(event) {
        jQuery(event.currentTarget).closest(".modal__overlay")[0].modal_close();
    }
}

export const modal_close = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_close());
