// jsmods/server/evt/auto_assign_port.js —— 自動分配連接埠鈕綁定（evt 層——一檔一事）
// 職責：auto_assign_port（[data-request_btn='auto_assign_port'] 點擊 → 向後端攞一個可用埠 → 填入 port 格）
// 結構同 server 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _auto_assign_port {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——settings_modal 範圍）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_auto_assign_port")
            .on("click.sc_ai_settings_auto_assign_port", "[data-modal='settings_modal'] [data-request_btn='auto_assign_port']", this.auto_assign_port.bind(this));
    }

    // 動作：自動分配連接埠（modal 根經 modal 狀態取——表單現值喺 modal 內）
    async auto_assign_port(event) {
        const j_button = jQuery(event.currentTarget);

        // spawn 取組（modal 狀態 j_modal／server 分頁 act）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const server_domain = await (await registry().sc_ai_settings()).server();
        const server_module = await server_domain.act().server();

        await server_module.settings_auto_assign_port(j_button, modal_module.j_modal);
    }
}

export const auto_assign_port = (function (instance) {
    return function () {

        return instance;
    };
})(new _auto_assign_port());
