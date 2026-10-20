// jsmods/callback/conn_status_item.js —— 連線狀態結果套上 sb_conn（callback 層——一檔一事）
// 職責：conn_status_item（health 報告 reports.conn（bridge 快查）→ sb_conn 已連線／未連線＋顏色——只此兩態）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _conn_status_item {
    constructor() {}

    // 套用連線狀態（is_alive → 已連線 ok／未連線 bad——只此兩態）
    async apply_conn_status(conn_result) {
        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        // 動態載入（registry——用先載）
        const i18n = registry().sc_i18n();

        // 判定：無結果（本輪未帶）→ 未連線（保守）
        if (!conn_result) {
            status_item_render_instance.set_item("sb_conn", i18n.get("status_bar.conn_offline"));
            status_item_render_instance.set_item_class("sb_conn", "bad");
            return;
        }

        // 判定：快查唔活 → 未連線
        if (conn_result.is_alive !== true) {
            status_item_render_instance.set_item("sb_conn", i18n.get("status_bar.conn_offline"));
            status_item_render_instance.set_item_class("sb_conn", "bad");
            return;
        }

        status_item_render_instance.set_item("sb_conn", i18n.get("status_bar.conn_online"));
        status_item_render_instance.set_item_class("sb_conn", "ok");
    }
}

export const conn_status_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _conn_status_item());
