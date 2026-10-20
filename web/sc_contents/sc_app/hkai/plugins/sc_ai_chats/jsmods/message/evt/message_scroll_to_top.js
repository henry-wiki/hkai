// jsmods/message/evt/message_scroll_to_top.js —— 到頂按鈕綁定（factory 單例——一檔一元件：撳；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——click 委派）② 動作（message_scroll_to_top——交 action 原語）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_scroll_to_top {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 到頂（上工具列「最頂」）
        jQuery(document)
            .off("click.sc_ai_chats_scroll_to_top")
            .on("click.sc_ai_chats_scroll_to_top", "[data-action='scroll_to_top']", this.message_scroll_to_top.bind(this));
    }

    // 使用者主動到頂（smooth——交 act 原語）
    async message_scroll_to_top() {
        // 橋接駁（同域 act——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const message_scroll_to_top_instance = await message_instance.act().message_scroll_to_top();

        message_scroll_to_top_instance.message_scroll_to_top(true);
    }

}

export const message_scroll_to_top = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_scroll_to_top());
