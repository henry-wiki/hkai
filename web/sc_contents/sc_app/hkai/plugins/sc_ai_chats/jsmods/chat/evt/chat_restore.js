// jsmods/chat/evt/chat_restore.js —— 還原對話 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派 [data-action='chat_restore']）② 動作（chat_restore——交 act）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _chat_restore {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 還原對話（API → 清單移除／瀏覽中被還原＝歸檔列面）
        jQuery(document)
            .off("click.sc_ai_chats_chat_restore")
            .on("click.sc_ai_chats_chat_restore", "[data-action='chat_restore']", this.chat_restore.bind(this));
    }

    // 還原（chat_restore——先確認，讀 data-confirm_msg → 交 act/chat_restore）
    async chat_restore(event) {
        // 卡根係 <a> —— 掣 click 要截斷（唔觸發導航／切換）
        event.preventDefault();
        event.stopPropagation();

        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const j_restore_button = jQuery(event.currentTarget);
        const j_chat_item = j_restore_button.closest(".sc_chat_card");

        // 判定：找不到對話列 → 不動作
        if (!j_chat_item.length) {
            return;
        }

        // 判定：使用者未確認 → 不動作（標準確認框——讀 data-confirm_msg）
        if (!await shared.sc_web_utils().is_confirmed(j_restore_button)) {
            return;
        }

        // 對話 id（列上嘅 data-chat_id）
        const chat_id = j_chat_item.attr("data-chat_id");

        // 橋接駁（同域 act——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_restore_instance = await chat_instance.act().chat_restore();

        chat_restore_instance.chat_restore(chat_id, j_chat_item);
    }
}

export const chat_restore = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_restore());
