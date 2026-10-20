// jsmods/message_window/act/restore_message.js —— 還原原語（act 層——一檔一事）
// 職責：restore_message（佔位 → 字典取成品 → 原位插返；成品已含 markdown／mermaid 結果——零 re-render、零網絡）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _restore_message {
    constructor() {}

    // 還原一條訊息（j_placeholder＝佔位；trim_map＝域根共享字典——觀察器交接由域根名冊管理自動跟）
    restore_message(j_placeholder, trim_map) {
        const message_id = j_placeholder.attr("data-message_id");
        const message_html = trim_map.get(message_id);

        // 判定：成品唔存在（換對話／重掛後）→ 唔還原（佔位照留）
        if (!message_html) {
            return;
        }

        // 字典交返出去（還原＝釋放字串——下次卸載重新入）
        trim_map.delete(message_id);

        j_placeholder.replaceWith(jQuery(message_html));
    }
}

export const restore_message = (function (instance) {
    return function () {

        return instance;
    };
})(new _restore_message());
