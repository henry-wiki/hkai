// jsmods/queue/evt/queue_cancel.js —— 等待列 chip 回收綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（queue_cancel）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _queue_cancel {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 等待列 chip（×）
        jQuery(document)
            .off("click.sc_ai_chats_queue_cancel")
            .on("click.sc_ai_chats_queue_cancel", "[data-action='queue_cancel']", this.queue_cancel.bind(this));
    }

    // 回收未開始回覆嘅訊息（chip × → prepare data（chip 嘅 queue_uid）→ 交 act/queue）
    async queue_cancel(event) {
        const j_cancel_button = jQuery(event.currentTarget);
        const j_chip = j_cancel_button.closest(".queue_item");

        // 判定：唔係 chip → 唔處理
        if (!j_chip.length) {
            return;
        }

        // mini loading（click 等待期——交 act 收尾除）
        j_cancel_button.addClass("loading");

        const queue_uid = j_chip.attr("data-queue_uid");

        // 橋接駁（同域 act——新方式）
        const queue_instance = await registry().sc_ai_chats().queue();
        const queue_dispatch_instance = await queue_instance.act().queue_dispatch();

        queue_dispatch_instance.queue_cancel(queue_uid, { j_cancel_button: j_cancel_button });
    }
}

export const queue_cancel = (function (instance) {
    return function () {

        return instance;
    };
})(new _queue_cancel());
