// jsmods/message/evt/message_delete.js —— 刪除訊息 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（message_delete——交 action）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_delete {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 刪除訊息（確認框 → API → 移除畫面）
        jQuery(document)
            .off("click.sc_ai_chats_message_delete")
            .on("click.sc_ai_chats_message_delete", "[data-action='message_delete']", this.message_delete.bind(this));
    }

    // 刪除（message_delete → prepare data（按鈕）→ 交 act/message）
    async message_delete(event) {
        const j_delete_button = jQuery(event.currentTarget);

        // 橋接駁（同域 act——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const message_delete_instance = await message_instance.act().message_delete();

        message_delete_instance.message_delete(j_delete_button);
    }
}

export const message_delete = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_delete());
