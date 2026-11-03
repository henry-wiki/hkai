// web/sc_contents/sc_app/hkai/plugins/sc_ai_status_bar/jsmods/status_bar/act/sync_status_items.js —— 既有 DOM 唯讀同步落 sb_*（callback 層——一檔一事）
// 職責：sync_status_items（模型列 → set_item 推值；專案／對話兩欄改由 project_fetch／chat_fetch 單一來源）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _sync_status_items {
    constructor() {}

    // 處理狀態 → sb_state（既有處理訊號：.activity_monitor.loading——唯讀觀察）
    async sync_state() {
        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        const j_loading_monitor = document.querySelector(".sc_thread__viewport .activity_monitor.loading");

        // 有 loading 中的活動監控 → 處理中（busy）
        if (j_loading_monitor) {
            status_item_render_instance.set_state("sb_state", "busy");
            return;
        }
        status_item_render_instance.set_state("sb_state", "idle");
    }
}

export const sync_status_items = (function (instance) {
    return function () {

        return instance;
    };
})(new _sync_status_items());
