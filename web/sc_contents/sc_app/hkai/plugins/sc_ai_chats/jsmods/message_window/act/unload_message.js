// jsmods/message_window/act/unload_message.js —— 卸載原語（act 層——一檔一事）
// 職責：unload_message（真 wrapper → 量高 → 存 outerHTML 成品落字典 → 換同高佔位；保高＝零跳動、成品含 SVG＝還原零 render）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _unload_message {
    constructor() {}

    // 卸載一條訊息（j_wrapper＝真節點；trim_map＝域根共享字典——觀察器交接由域根名冊管理自動跟）
    unload_message(j_wrapper, trim_map) {
        const message_id = j_wrapper.attr("data-message_id");
        const message_height = j_wrapper[0].offsetHeight;

        // 判定：有 id → 成品入字典（還原靠 id 搵返）
        if (message_id) {
            trim_map.set(message_id, j_wrapper[0].outerHTML);
        }

        // 同高佔位（高度釘死——佈局零變、零跳動）
        const j_placeholder = jQuery("<div class='message_placeholder'></div>");
        j_placeholder.attr("data-message_id", message_id);
        j_placeholder.css("height", message_height + "px");

        j_wrapper.replaceWith(j_placeholder);
    }
}

export const unload_message = (function (instance) {
    return function () {

        return instance;
    };
})(new _unload_message());
