// jsmods/chat/act/chat_item_remove.js —— 對話項移除（act 層——一檔一事；主動作＝檔名）
// 職責：chat_item_remove（按 chat_sid 移除該對話項 DOM——由 chat_list_refresh 迴圈逐個呼叫）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_item_remove {
    constructor() {}

    // 按 chat_sid 移除單一對話項
    chat_item_remove(chat_sid) {
        const j_chat_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']");

        j_chat_item.remove();
    }
}

export const chat_item_remove = (function (instance) {
    return function () {
        return instance;
    };
})(new _chat_item_remove());
