// jsmods/chat_option/evt/chat_mode.js —— chat mode 按鈕綁定（evt 層——一檔一事）
// 職責：chat_mode（ask/plan/build 點擊 → 寫選取（model_context 單一 writer）→ 全按鈕 active 重刷；提交交 model_info observer 自動接手）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_mode {
    constructor() {}

    // 綁定註冊（由域根 init 呼叫——一次；document 委派）
    init() {
        jQuery(document)
            .off("click.sc_ai_chats_chat_mode")
            .on("click.sc_ai_chats_chat_mode", "[data-btn='chat_mode']", this.chat_mode.bind(this));
    }

    // chat mode 按鈕切換（寫選取 → active 重刷；落地交 observer）
    async chat_mode(event) {
        // 點擊的 mode 值（按鈕 data-value——本次切換的來源）
        const chat_mode_value = jQuery(event.currentTarget).data("value");

        // 寫入選取（selector 域 model_context——單一 writer；寫完 observer 見變自動提交）
        const model_selector_instance = await (await registry().sc_ai_providers()).model_selector();
        const model_context_instance = await model_selector_instance.act().model_context();
        model_context_instance.set_chat_mode(chat_mode_value);

        // 全按鈕重刷 active（值相符 → 加；其餘 → 去）
        const chat_option_domain = await registry().sc_ai_chats().chat_option();
        const refresh_chat_mode_active_instance = await chat_option_domain.act().refresh_chat_mode_active();
        refresh_chat_mode_active_instance.refresh_chat_mode_active(chat_mode_value);
    }
}

export const chat_mode = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_mode());
