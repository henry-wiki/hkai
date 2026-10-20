// jsmods/message/evt/message_scroll_to_bottom.js —— 回底按鈕綁定（factory 單例——一檔一元件：撳；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——click 委派）② 動作（message_scroll_to_bottom——交 action 原語）（按鈕顯示歸 message_intersection_observer）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_scroll_to_bottom {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 回到最新（回底按鈕）
        jQuery(document)
            .off("click.sc_ai_chats_scroll_to_bottom")
            .on("click.sc_ai_chats_scroll_to_bottom", "[data-action='scroll_to_bottom']", this.message_scroll_to_bottom.bind(this));
    }

    // 使用者主動回底（smooth——捲動事件會恢復跟隨＋切換按鈕）
    async message_scroll_to_bottom() {
        // 橋接駁（同域 act——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const message_scroll_to_bottom_instance = await message_instance.act().message_scroll_to_bottom();

        message_scroll_to_bottom_instance.message_scroll_to_bottom(true);
    }

}

export const message_scroll_to_bottom = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_scroll_to_bottom());
