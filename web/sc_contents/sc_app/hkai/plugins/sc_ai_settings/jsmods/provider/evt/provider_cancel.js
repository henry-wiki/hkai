// jsmods/provider/evt/provider_cancel.js —— provider 取消重設觸發（evt 層——一事件一檔）
// 職責：provider_cancel（[data-btn='cancel_provider_settings'] 點擊 → 交 provider 動作——取消回唯讀）
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _provider_cancel {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_provider_cancel")
            .on("click.sc_ai_settings_provider_cancel", "[data-modal='settings_modal'] [data-btn='cancel_provider_settings']", this.provider_cancel.bind(this));
    }

    // 動作：取消該列重設（掣＝事件來源；回唯讀由 act 切態）
    async provider_cancel(event) {
        const j_button = jQuery(event.currentTarget);

        // spawn 取組（provider 分頁）
        const provider_domain = await (await registry().sc_ai_settings()).provider();
        const provider_module = await provider_domain.act().provider();

        provider_module.cancel_provider_reset(j_button);
    }
}

export const provider_cancel = (function (instance) {
    return function () {

        return instance;
    };
})(new _provider_cancel());
