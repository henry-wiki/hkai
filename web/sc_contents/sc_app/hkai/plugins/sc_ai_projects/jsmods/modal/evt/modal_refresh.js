// jsmods/modal/evt/modal_refresh.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（modal_refresh——重新整理（footer 左側共用鈕））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _modal_refresh {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 重新整理（footer 共用鈕——當前 tab 內容重載；規則頁＝右側同儲存並列）
        jQuery(document)
            .off("click.sc_ai_projects_refresh")
            .on("click.sc_ai_projects_refresh", "[data-btn='project_refresh']", this.modal_refresh.bind(this));
    }

    // 重新整理（footer 共用鈕——觸發一次當前 tab 嘅切換＝load self again）
    modal_refresh(event) {
        const j_refresh_button = jQuery(event.currentTarget);
        const j_modal = j_refresh_button.closest(".modal__overlay");


        // 當前 tab 再觸發一次 click（重行走同一條切 tab 載入路——自己載返自己）
        j_modal.find(".modal_tab.on").trigger("click");
    }
}

export const modal_refresh = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_refresh());
