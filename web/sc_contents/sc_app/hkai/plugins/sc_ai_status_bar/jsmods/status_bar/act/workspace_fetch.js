// jsmods/status_bar/act/workspace_fetch.js —— workspace 區動態載入（act 層——一檔一事）
// 職責：workspace_fetch（叫 req 攞返 workspace 區 status bar HTML → 換返畫面上嗰區——值由後端出；sb_model 值＝composer 現況（客戶端狀態）→ 換完由既有同步補返）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _workspace_fetch {
    constructor() {}

    // workspace 區重取（換返 .sc_statusbar--workspace 整個 wrapper）
    async workspace_fetch() {
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const workspace_status_bar_fetch_instance = await status_bar_domain.req().workspace_status_bar_fetch();

        await workspace_status_bar_fetch_instance.workspace_status_bar_fetch({
            success: async function (response_data) {
                jQuery(".sc_statusbar--workspace").replaceWith(response_data.html.status_bar);

                // 模型值回填（sb_model＝composer 現況（客戶端狀態——後端唔知）——區塊換返後由既有同步補返）
                const sync_status_items_instance = await status_bar_domain.act().sync_status_items();
                sync_status_items_instance.sync_model();
            },
            error: async function (response_data) {
                // 失敗（維持現狀——等下次 URL 變化再攞）
            },
        });
    }
}

export const workspace_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _workspace_fetch());
