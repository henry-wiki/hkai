// jsmods/chat/act/chat_card_move_to_top.js —— 對話卡移頂（act 層——一檔一事；主動作＝檔名）
// 職責：chat_card_move_to_top（把指定對話卡移到 .sc_chat_list 最頂——送出訊息後即時反映）
// 上游：jsmods/prompt/act/act_prompt_send.js（送出成功後呼叫）
// 下游：無（純 DOM 移動）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_card_move_to_top {
    constructor() {}

    // 找卡（兩種身份形：普通 chat_sid／歸檔 archive@chat_sid）
    _find_chat_card(chat_sid) {
        const j_plain_card = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']");

        // 判定：普通形搵到 → 回
        if (j_plain_card.length > 0) {
            return j_plain_card;
        }

        return jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='archive@" + chat_sid + "']");
    }

    // 移到最頂（已喺最頂 → 唔動）
    chat_card_move_to_top(chat_sid) {
        const j_chat_list = jQuery(".sc_chat_list");
        const j_chat_card = this._find_chat_card(chat_sid);

        // 判定：清單或卡片唔存在 → 唔動
        if (j_chat_list.length === 0 || j_chat_card.length === 0) {
            return;
        }

        // 判定：已經係第一張 → 唔動（免無謂 DOM 變動）
        if (j_chat_list.children(".sc_chat_card").first()[0] === j_chat_card[0]) {
            return;
        }

        j_chat_list.prepend(j_chat_card);
    }
}

export const chat_card_move_to_top = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_card_move_to_top());
