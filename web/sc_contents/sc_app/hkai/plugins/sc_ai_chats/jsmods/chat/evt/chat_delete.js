// jsmods/chat/evt/chat_delete.js —— 刪除對話 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派 [data-action='chat_delete']）② 動作（chat_delete——確認後交 action）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _chat_delete {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 刪除對話（確認框 → API → 清單移除／無痕切走）
        jQuery(document)
            .off("click.sc_ai_chats_chat_delete")
            .on("click.sc_ai_chats_chat_delete", "[data-action='chat_delete']", this.chat_delete.bind(this));
    }

    // 刪除（chat_delete——先確認，讀 data-confirm_msg → 交 act/chat_delete）
    async chat_delete(event) {
        // 卡根係 <a> —— 掣 click 要截斷（唔觸發導航／切換）
        event.preventDefault();
        event.stopPropagation();

        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const j_delete_button = jQuery(event.currentTarget);
        const j_chat_item = j_delete_button.closest(".sc_chat_card");

        // 判定：找不到對話列 → 不動作
        if (!j_chat_item.length) {
            return;
        }

        // 判定：使用者未確認 → 不動作
        if (!await shared.sc_web_utils().is_confirmed(j_delete_button)) {
            return;
        }

        // 對話 id（列上嘅 data-chat_id）
        const chat_id = j_chat_item.attr("data-chat_id");

        // 橋接駁（同域 act——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_delete_instance = await chat_instance.act().chat_delete();

        chat_delete_instance.chat_delete(chat_id, j_chat_item);
    }
}

export const chat_delete = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_delete());
