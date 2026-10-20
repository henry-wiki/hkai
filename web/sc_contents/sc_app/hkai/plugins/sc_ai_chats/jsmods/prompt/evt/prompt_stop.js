// jsmods/prompt/evt/prompt_stop.js —— 取消查詢綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_stop）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_stop {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 取消查詢
        jQuery(document)
            .off("click.sc_ai_chats_prompt_stop")
            .on("click.sc_ai_chats_prompt_stop", "[data-action='stop']", this.prompt_stop.bind(this));
    }

    // 停止（[data-action='stop'] → prepare data（回覆中嗰條嘅 message_id）→ 交 act/prompt_stop）
    async prompt_stop(event) {
        const j_stop_button   = jQuery(event.currentTarget);
        const j_dock_textarea = jQuery("#sc_input");

        const request_data = {};

        // 目標：回覆中嗰條（細 status 三態——取第一條嘅 message_id）
        const j_reply_message = jQuery(".sc_thread__viewport .message_wrapper[data-status='connecting'], .sc_thread__viewport .message_wrapper[data-status='summarizing'], .sc_thread__viewport .message_wrapper[data-status='replying']").first();

        request_data.message_id = j_reply_message.attr("data-message_id");

        // 判定：冇回覆中 → 唔處理
        if (!request_data.message_id) {
            return;
        }

        // mini loading（click 等待期——交 act 收尾除）
        j_stop_button.addClass("loading");

        // 橋接駁（同域 act——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_stop_instance = await prompt_instance.act().prompt_stop();

        prompt_stop_instance.prompt_stop(request_data, {
            j_stop_button   : j_stop_button,
            j_dock_textarea : j_dock_textarea
        });
    }
}

export const prompt_stop = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_stop());
