// jsmods/bind/server_refresh.js —— Server 重新整理鈕綁定（bind 層——一檔一事）
// 職責：server_refresh（[data-btn='server_refresh'] 點擊 → 重載 server 分頁內容：狀態／按鈕／日誌一次過重畫）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _server_refresh {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_server_refresh")
            .on("click.sc_ai_settings_server_refresh", "[data-modal='settings_modal'] [data-btn='server_refresh']", this.server_refresh.bind(this));
    }

    // 動作：重載 server 分頁（後端重畫——loading 遮罩由 modal reload_section 負責）
    async server_refresh() {
        // 動態載入（registry——用先載）

        const server_domain = await (await registry().sc_ai_settings()).server();
        const server_module = await server_domain.act().server();

        await server_module.reload_server_section();
    }
}

export const server_refresh = (function (instance) {
    return function () {

        return instance;
    };
})(new _server_refresh());
