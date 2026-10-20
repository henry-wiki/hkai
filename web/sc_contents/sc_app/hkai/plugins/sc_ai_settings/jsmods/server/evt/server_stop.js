// jsmods/bind/server_stop.js —— Server 停用鈕綁定（bind 層——一檔一事）
// 職責：server_stop（[data-request_btn='stop_server'] 點擊 → 停用 server 流程）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _server_stop {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_server_stop")
            .on("click.sc_ai_settings_server_stop", "[data-modal='settings_modal'] [data-request_btn='stop_server']", this.server_stop.bind(this));
    }

    // 動作：停止 server（modal 根經 modal 狀態取——狀態重載在 act 層）
    async server_stop(event) {
        // 動態載入（registry——用先載）

        const j_button = jQuery(event.currentTarget);

        // spawn 取組（modal 狀態 j_modal／server 分頁）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const server_domain = await (await registry().sc_ai_settings()).server();
        const server_module = await server_domain.act().server();

        server_module.settings_stop_server(j_button, modal_module.j_modal);
    }
}

export const server_stop = (function (instance) {
    return function () {

        return instance;
    };
})(new _server_stop());
