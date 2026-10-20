// jsmods/bind/server_start.js —— Server 啟動鈕綁定（bind 層——一檔一事）
// 職責：server_start（[data-request_btn='start_server'] 點擊 → 啟動 server 流程）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _server_start {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_server_start")
            .on("click.sc_ai_settings_server_start", "[data-modal='settings_modal'] [data-request_btn='start_server']", this.server_start.bind(this));
    }

    // 動作：啟動 server（modal 根經 modal 狀態取——表單現值喺 modal 內）
    async server_start(event) {
        // 動態載入（registry——用先載）

        const j_button = jQuery(event.currentTarget);

        // spawn 取組（modal 狀態 j_modal／server 分頁）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const server_domain = await (await registry().sc_ai_settings()).server();
        const server_module = await server_domain.act().server();

        server_module.settings_start_server(j_button, modal_module.j_modal);
    }
}

export const server_start = (function (instance) {
    return function () {

        return instance;
    };
})(new _server_start());
