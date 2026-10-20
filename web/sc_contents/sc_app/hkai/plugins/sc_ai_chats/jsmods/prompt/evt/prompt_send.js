// jsmods/prompt/evt/prompt_send.js —— 發送訊息綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_send）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_send {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 發送訊息
        jQuery(document)
            .off("click.sc_ai_chats_prompt_send")
            .on("click.sc_ai_chats_prompt_send", "[data-action='send']", this.prompt_send.bind(this));
    }

    // 發送（[data-action='send'] → prepare data（訊息內容＋手柄）→ 交 act/prompt_send）
    async prompt_send(event) {
        const j_send_button   = jQuery(event.currentTarget);
        const j_dock_textarea = jQuery("#sc_input");

        const request_data = {};
        request_data.message_id = 0;
        request_data.content    = j_dock_textarea.val().trim();

        // 橋接駁（同域 act——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_instance = await prompt_instance.act().prompt_send();

        prompt_send_instance.prompt_send(request_data, {
            j_send_button   : j_send_button,
            j_dock_textarea : j_dock_textarea
        });
    }
}

export const prompt_send = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_send());
