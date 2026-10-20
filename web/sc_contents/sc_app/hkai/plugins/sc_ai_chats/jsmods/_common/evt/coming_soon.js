// jsmods/_common/evt/coming_soon.js —— 即將推出 placeholder click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——document selector 委派 [data-cmd='coming_soon']）② 動作（coming_soon——sc_alert 彈窗 popup）
// 適用：任何未開發掣自帶 data-cmd='coming_soon' 即通（topbar／composer／about 檢查更新——統一寫法）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _coming_soon {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 未開發掣（placeholder——[data-cmd='coming_soon'] 全頁通用）
        jQuery(document)
            .off("click.sc_ai_chats_coming_soon")
            .on("click.sc_ai_chats_coming_soon", "[data-cmd='coming_soon']", this.coming_soon.bind(this));
    }

    // 即將推出（sc_alert 彈窗——popup alert，撳確認先閂）
    async coming_soon(event) {
        // 動態載入（registry——用先載）
        const i18n = registry().sc_i18n();
        const shared = registry().shared();

        shared.sc_alert().primary(i18n.get("common.coming_soon"));
    }
}

export const coming_soon = (function (instance) {
    return function () {

        return instance;
    };
})(new _coming_soon());
