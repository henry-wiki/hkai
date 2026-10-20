// jsmods/message/act/message_above_count_check.js —— 判①：視窗頂以上條數達標？（act 層——一檔一事）
// 職責：message_above_count_check（判①——完全喺視窗頂以上嘅 .message_wrapper 條數 ≥ 一倍頁（data-message_limit）＝達標；回傳布林）
// 幾何取法：getBoundingClientRect 視口座標（逐條對視窗頂——唔使換算 scrollTop）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_above_count_check {
    constructor() {}

    // 判定：條數達標？（達標＝true——上面準備夠一倍頁）
    message_above_count_check() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 視窗頂（視口座標——「上面」嘅界線）
        const viewport_top = j_messages[0].getBoundingClientRect().top;

        // 一倍頁（目標條數——窗口每頁條數；載入時同游標一齊落）
        const message_limit = parseInt(j_messages.attr("data-message_limit"), 10);

        let above_count = 0;

        // 逐條數：完全喺視窗頂以上（條底唔過視窗頂）先算
        j_messages.children(".message_wrapper").each(function () {
            const message_rect = this.getBoundingClientRect();

            // 判定：條底唔過視窗頂 → 計一條
            if (message_rect.bottom <= viewport_top) {
                above_count += 1;
            }
        });

        return above_count >= message_limit;
    }
}

export const message_above_count_check = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_above_count_check());
