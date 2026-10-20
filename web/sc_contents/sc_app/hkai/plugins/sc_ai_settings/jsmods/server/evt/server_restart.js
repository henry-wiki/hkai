// jsmods/bind/server_restart.js —— Server 重新啟動鈕綁定（bind 層——一檔一事）
// 職責：server_restart（[data-request_btn='restart_server'] 點擊 → 重新啟動 server 流程）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _server_restart {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_server_restart")
            .on("click.sc_ai_settings_server_restart", "[data-modal='settings_modal'] [data-request_btn='restart_server']", this.server_restart.bind(this));
    }

    // 動作：重新啟動 server（modal 根經 modal 狀態取——狀態重載在 act 層）
    async server_restart(event) {
        // 動態載入（registry——用先載）

        const j_button = jQuery(event.currentTarget);

        // spawn 取組（modal 狀態 j_modal／server 分頁）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const server_domain = await (await registry().sc_ai_settings()).server();
        const server_module = await server_domain.act().server();

        server_module.settings_restart_server(j_button, modal_module.j_modal);
    }
}

export const server_restart = (function (instance) {
    return function () {

        return instance;
    };
})(new _server_restart());
