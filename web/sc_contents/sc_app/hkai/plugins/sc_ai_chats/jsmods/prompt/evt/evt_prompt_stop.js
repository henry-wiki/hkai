// jsmods/prompt/evt/evt_prompt_stop.js —— 取消查詢綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_stop）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _evt_prompt_stop {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 取消查詢
        jQuery(document)
            .off("click.sc_ai_chats_prompt_stop")
            .on("click.sc_ai_chats_prompt_stop", "[data-action='stop']", this.prompt_stop.bind(this));
    }

    // 停止（[data-action='stop'] → 交 act/act_prompt_stop；唔查訊息狀態——目標由後端按對話反查）
    async prompt_stop(event) {
        const j_stop_button   = jQuery(event.currentTarget);
        const j_dock_textarea = jQuery("#sc_input");

        const request_data = {};

        // 判定：送出仍在途（訊息未落地，場上仲未有目標）→ 記等停旗標，等 prompt_send 回應一到即取消（空窗期配套）
        if (jQuery("[data-action='send']").attr("data-state") === "loading") {
            jQuery(document).data("sc_stop_pending", true);

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
})(new _evt_prompt_stop());
