// jsmods/modal/act/modal_tab_switch.js —— modal tab 切換（action 層——一 action 一檔）
// 職責：modal_tab_switch（.modal_tab／.modal_pane 以 data-tab_id／data-pane 對應）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _modal_tab_switch {
    constructor() {}

    // 切換 tab（.modal_tab 與 .modal_pane 以 data-tab_id / data-pane 對應）
    modal_tab_switch(j_modal, tab_name) {
        j_modal.find(".modal_tab").removeClass("on");
        j_modal.find(".modal_tab[data-tab_id='" + tab_name + "']").addClass("on");

        j_modal.find(".modal_pane").removeClass("on");
        j_modal.find(".modal_pane[data-pane='" + tab_name + "']").addClass("on");
    }
}

export const modal_tab_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_tab_switch());
