// jsmods/message/act/message_scroll_to_bottom.js —— 捲動原語（act 層——一檔一事）
// 職責：message_scroll_to_bottom（捲到底——原生 scrollTo；smooth＝使用者主動回底、auto＝自動跟隨）
// 備註：「係唔係最底」幾何＝觀察者、「按鈕顯示」＝evt/message_scroll_to_bottom.js——本檔零計算
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_scroll_to_bottom {
    constructor() {}

    // 捲到底部（smooth = 使用者主動回底；自動跟隨用 auto 立即；jQuery 無 options 版 scrollTo——走原生元素）
    message_scroll_to_bottom(smooth = false) {
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：自動捲動（程式側——跳底／釘底）→ 寫低落點（畀「係唔係最底」快照識別自己嘅回聲——程式捲動唔入帳）
        if (!smooth) {
            const scroll_height = j_messages.prop("scrollHeight");
            const viewport_height = j_messages.prop("clientHeight");
            const landing_scroll_top = Math.max(0, scroll_height - viewport_height);

            j_messages.data("programmatic_scroll_landing_top", landing_scroll_top);
        }

        j_messages[0].scrollTo({
            top: j_messages.prop("scrollHeight"),
            behavior: smooth ? "smooth" : "auto",
        });
    }
}

export const message_scroll_to_bottom = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_scroll_to_bottom());
