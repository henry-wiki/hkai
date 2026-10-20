// jsmods/callback/sync_status_items.js —— 既有 DOM 唯讀同步落 sb_*（callback 層——一檔一事）
// 職責：sync_status_items（專案欄／對話欄／模型列／處理狀態 → set_item／set_state 推值）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _sync_status_items {
    constructor() {}

    // 專案欄 → sb_project（專案清單為 JS 動態載入——清單重繪後由 sc_ai_projects 再呼叫）
    async sync_project() {
        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        const j_active_project_name = document.querySelector(".sc_project_list .sc_project_card--active .sc_project_card__name");

        // 無選取專案 → 維持預設（—）
        if (!j_active_project_name) {
            status_item_render_instance.set_item("sb_project", "—");
            return;
        }

        status_item_render_instance.set_item("sb_project", j_active_project_name.textContent);
    }

    // 對話欄 → sb_chat
    async sync_chat() {
        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        // 動態載入（registry——用先載）
        const i18n = registry().sc_i18n();

        const j_active_chat_title = document.querySelector(".sc_chat_list .sc_chat_card--active .sc_chat_card__title");

        // 無選取對話 → 顯示未選擇
        if (!j_active_chat_title) {
            status_item_render_instance.set_item("sb_chat", i18n.get("status_bar.no_chat"));
            return;
        }
        status_item_render_instance.set_item("sb_chat", j_active_chat_title.textContent);
    }

    // 模型列 → sb_model（model · mode——來源：模型顯示欄＋目前 active 模式鈕）
    async sync_model() {
        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        const j_model_id = document.querySelector(".sc_composer__model_name");
        const j_active_mode = document.querySelector("[data-btn='chat_mode'].sc_btn--active");

        let model_id = "";
        let chat_mode = "";
        if (j_model_id) {
            model_id = j_model_id.textContent;
        }
        if (j_active_mode) {
            chat_mode = j_active_mode.dataset.value;
        }

        // 兩者皆空 → 維持預設
        if (model_id === "" && chat_mode === "") {
            return;
        }
        // 只有 model → 顯示 model
        if (chat_mode === "") {
            status_item_render_instance.set_item("sb_model", model_id);
            return;
        }
        // 只有 mode → 顯示 mode
        if (model_id === "") {
            status_item_render_instance.set_item("sb_model", chat_mode);
            return;
        }
        status_item_render_instance.set_item("sb_model", model_id + " · " + chat_mode);
    }

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
