// jsmods/message/act/message_above_height_check.js —— 判②：視窗頂以上高度達標？（act 層——一檔一事）
// 職責：message_above_height_check（判②——視窗頂以上高度（＝scrollTop 已捲過嘅內容）≥ 一倍視窗高（clientHeight）＝達標；回傳布林）
// 用盡平台：scrollTop／clientHeight 兩個現成數字——單一原子判斷
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_above_height_check {
    constructor() {}

    // 判定：高度達標？（達標＝true——上面準備夠一倍視窗高）
    message_above_height_check() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 上面高度（視窗頂以上——已捲過嘅內容）
        const above_height = j_messages[0].scrollTop;

        // 一倍視窗高（目標高度）
        const viewport_height = j_messages[0].clientHeight;

        return above_height >= viewport_height;
    }
}

export const message_above_height_check = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_above_height_check());
