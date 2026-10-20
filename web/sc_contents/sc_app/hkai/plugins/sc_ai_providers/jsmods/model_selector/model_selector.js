// jsmods/model_selector/model_selector.js —— model_selector 域（唯一入口——出入口掛喺 prototype）
// 用途：import { model_selector } from "./model_selector.js" 拎單例——model_selector().evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _model_selector {
    constructor() {
        this.inited = false;

        // modal 共用（開 modal 者 set——各組經域例項 get；單一持有）
        this.j_modal = null;

        // model info 共用（開 modal 時預備——composer hidden fields 快照；單一持有）
        this.model_info = {};
    }

    // 啟動域（sc_ai_providers.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（selector evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }

    // ── modal 共用（get／set 全喺度——各組經域例項存取）──

    // 存 modal 元素參照（開 modal 後灌入——各組動作嘅 DOM 根）
    set_j_modal(j_modal) {
        this.j_modal = j_modal;
    }

    // 取 modal 元素參照（modal 未開 → null）
    get_j_modal() {
        return this.j_modal;
    }

    // 取左欄 provider 項目（.side_provider_item——modal 未開 → null）
    get_side_provider_items() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find(".side_provider_item");
    }

    // 取左欄 provider 按鈕（.side_provider_tab_button——modal 未開 → null）
    get_side_provider_tab_buttons() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find(".side_provider_tab_button");
    }

    // 取 table wrap 容器（modal 未開 → null）
    get_table_wrap() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find("#model_selector_table_wrap");
    }

    // 取全部 model 列（modal 未開 → null）
    get_tr_model_rows() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find("tr.model_selector_model_row");
    }

    // 取選中 model 列（selected——modal 未開 → null）
    get_tr_model_row_selected() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        const j_tr_model_rows = this.get_tr_model_rows();
        return j_tr_model_rows.filter(".selected");
    }

    // ── model info 共用（get／set 全喺度——各組經域例項存取）──

    // 存當前 model info（開 modal 時預備——composer hidden fields 快照：provider_id／model_id／reasoning_effort）
    set_model_info(model_info) {
        this.model_info = model_info;
    }

    // 取當前 model info（開場預備嘅 composer 快照——context 校正用）
    get_model_info() {
        return this.model_info;
    }
}

// 連接（出入口掛上 prototype）
_model_selector.prototype.evt = function () { return new _model_selector_evt_bridge(); };
_model_selector.prototype.act = function () { return new _model_selector_act_bridge(); };
_model_selector.prototype.req = function () { return new _model_selector_req_bridge(); };

export const model_selector = (function (instance) {
    return function () {

        return instance;
    };
})(new _model_selector());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _model_selector_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.bind_model_picker()).init();
        (await this.switch_model_item()).init();
        (await this.filter_provider_list()).init();
        (await this.filter_model_list()).init();
        (await this.switch_reasoning_effort()).init();
        (await this.apply_model()).init();
        (await this.clear_selection()).init();
        (await this.refresh_model_list()).init();
        (await this.switch_provider_item()).init();
    }
    async bind_model_picker()       { return (await import("./evt/bind_model_picker.js")).bind_model_picker(); }
    async switch_model_item()       { return (await import("./evt/switch_model_item.js")).switch_model_item(); }
    async filter_provider_list()    { return (await import("./evt/filter_provider_list.js")).filter_provider_list(); }
    async filter_model_list()       { return (await import("./evt/filter_model_list.js")).filter_model_list(); }
    async switch_reasoning_effort() { return (await import("./evt/switch_reasoning_effort.js")).switch_reasoning_effort(); }
    async apply_model()             { return (await import("./evt/apply_model.js")).apply_model(); }
    async clear_selection()         { return (await import("./evt/clear_selection.js")).clear_selection(); }
    async refresh_model_list()      { return (await import("./evt/refresh_model_list.js")).refresh_model_list(); }
    async switch_provider_item()    { return (await import("./evt/switch_provider_item.js")).switch_provider_item(); }
}

// act（動作——開單叫工作）
class _model_selector_act_bridge {
    constructor() {}
    init() {

    }
    async model_picker()                { return (await import("./act/model_picker.js")).model_picker(); }
    async modal_action()                { return (await import("./act/modal_action.js")).modal_action(); }
    async render_model_list()           { return (await import("./act/render_model_list.js")).render_model_list(); }
    async render_modal_footer()         { return (await import("./act/render_modal_footer.js")).render_modal_footer(); }
    async render_modal_reasoning_efforts() { return (await import("./act/render_modal_reasoning_efforts.js")).render_modal_reasoning_efforts(); }
    async model_activity()              { return (await import("./act/model_activity.js")).model_activity(); }
    async model_context()               { return (await import("./act/model_context.js")).model_context(); }
    async refresh_model_display()       { return (await import("./act/refresh_model_display.js")).refresh_model_display(); }
    async switch_model_item()           { return (await import("./act/switch_model_item.js")).switch_model_item(); }
    async switch_provider_item()        { return (await import("./act/switch_provider_item.js")).switch_provider_item(); }
    async switch_reasoning_effort()     { return (await import("./act/switch_reasoning_effort.js")).switch_reasoning_effort(); }
    async apply_model()                 { return (await import("./act/apply_model.js")).apply_model(); }
}

// req（出街工作）
class _model_selector_req_bridge {
    constructor() {}
    init() {

    }
    async fetch_providers()             { return (await import("./req/fetch_providers.js")).fetch_providers(); }
    async fetch_models()                { return (await import("./req/fetch_models.js")).fetch_models(); }
    async redownload_provider_models() { return (await import("./req/redownload_provider_models.js")).redownload_provider_models(); }
}
