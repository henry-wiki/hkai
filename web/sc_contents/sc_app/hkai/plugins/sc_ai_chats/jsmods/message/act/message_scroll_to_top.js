// jsmods/message/act/message_scroll_to_top.js —— 捲動原語（act 層——一檔一事）
// 職責：message_scroll_to_top（捲到頂——原生 scrollTo；smooth＝使用者主動上頂）
// 備註：本檔零計算——幾何判斷歸觀察者（evt/message_intersection_observer.js）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_scroll_to_top {
    constructor() {}

    // 捲到頂部（smooth = 使用者主動上頂；auto = 立即）
    message_scroll_to_top(smooth = false) {
        const j_messages = jQuery(".sc_thread__viewport");

        j_messages[0].scrollTo({
            top: 0,
            behavior: smooth ? "smooth" : "auto",
        });
    }
}

export const message_scroll_to_top = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_scroll_to_top());
