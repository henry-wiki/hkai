// jsmods/queue/evt/queue_clear.js —— 清除排隊綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（queue_clear）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _queue_clear {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 上工具列：清除排隊
        jQuery(document)
            .off("click.sc_ai_chats_queue_clear")
            .on("click.sc_ai_chats_queue_clear", "[data-action='queue_clear']", this.queue_clear.bind(this));
    }

    // 清除等待列（整排清空——交 act/queue_dispatch）
    async queue_clear() {
        // 橋接駁（同域 act——新方式）
        const queue_instance = await registry().sc_ai_chats().queue();
        const queue_dispatch_instance = await queue_instance.act().queue_dispatch();

        queue_dispatch_instance.queue_clear();
    }
}

export const queue_clear = (function (instance) {
    return function () {

        return instance;
    };
})(new _queue_clear());
