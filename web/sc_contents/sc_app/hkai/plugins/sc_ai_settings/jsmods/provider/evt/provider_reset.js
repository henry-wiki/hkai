// jsmods/provider/evt/provider_reset.js —— provider 重設觸發（evt 層——一事件一檔）
// 職責：provider_reset（[data-btn='reset_provider_settings'] 點擊 → 交 provider 動作——唯讀入編輯）
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _provider_reset {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_provider_reset")
            .on("click.sc_ai_settings_provider_reset", "[data-modal='settings_modal'] [data-btn='reset_provider_settings']", this.provider_reset.bind(this));
    }

    // 動作：重設該列 provider（掣＝事件來源；切態／提交由 act 依現態判）
    async provider_reset(event) {
        const j_button = jQuery(event.currentTarget);

        // spawn 取組（provider 分頁）
        const provider_domain = await (await registry().sc_ai_settings()).provider();
        const provider_module = await provider_domain.act().provider();

        provider_module.reset_provider_settings(j_button);
    }
}

export const provider_reset = (function (instance) {
    return function () {

        return instance;
    };
})(new _provider_reset());
