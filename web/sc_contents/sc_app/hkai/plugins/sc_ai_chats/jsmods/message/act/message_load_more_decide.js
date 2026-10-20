// jsmods/message/act/message_load_more_decide.js —— 綜合判斷（act 層——一檔一事）
// 職責：message_load_more_decide（收兩個判斷結果（判①條數／判②高度）→ 綜合——兩項都達標＝唔使再入（false）；任一未達＝要再入（true））
// 註：兩個判斷各自檔案（判① message_above_count_check／判② message_above_height_check）——本檔只綜合，唔自己判斷
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_load_more_decide {
    constructor() {}

    // 綜合：要唔要再入？（count_ok／height_ok＝兩個判斷結果；兩項達標＝false）
    message_load_more_decide(count_ok, height_ok) {
        // 判定：兩項都達標 → 唔使再入
        if (
            count_ok
            && height_ok
        ) {
            return false;
        }

        return true;
    }
}

export const message_load_more_decide = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_load_more_decide());
