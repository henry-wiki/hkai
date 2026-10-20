// jsmods/bench_modal/bench_modal.js —— bench_modal 域（唯一入口——出入口掛喺 prototype）
// 用途：import { bench_modal } from "./bench_modal.js" 拎單例——bench_modal().evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _bench_modal {
    constructor() {
        this.inited = false;

        // modal 共用（開 modal 者 set——各組經域例項 get；單一持有）
        this.j_modal = null;
    }

    // 啟動域（sc_ai_benchmark.init 召集——事件綁定；重複呼叫只行一次）
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

    // 取左欄 bench_source 項目（.side_bench_source_item——modal 未開 → null）
    get_side_bench_source_items() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find(".side_bench_source_item");
    }

    // 取左欄 bench_source 按鈕（.side_bench_source_tab_button——modal 未開 → null）
    get_side_bench_source_tab_buttons() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find(".side_bench_source_tab_button");
    }

    // 取來源 tab 按鈕（.modal__tabs .modal_tab——modal 未開 → null）
    get_bench_source_tab_buttons() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find(".modal__tabs .modal_tab");
    }

    // 取全部 model 列（.model_selector_model_row——modal 未開 → null）
    get_tr_model_rows() {
        if (!this.j_modal)
            return null;

        if (!this.j_modal.length)
            return null;

        return this.j_modal.find("tr.model_selector_model_row");
    }
}

// 連接（出入口掛上 prototype）
_bench_modal.prototype.evt = function () { return new _bench_modal_evt_bridge(); };
_bench_modal.prototype.act = function () { return new _bench_modal_act_bridge(); };
_bench_modal.prototype.req = function () { return new _bench_modal_req_bridge(); };

export const bench_modal = (function (instance) {
    return function () {

        return instance;
    };
})(new _bench_modal());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _bench_modal_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.bind_model_picker()).init();
        (await this.filter_bench_source_list()).init();
        (await this.filter_model_list()).init();
        (await this.refresh_model_list()).init();
        (await this.switch_bench_source_item()).init();
        (await this.switch_bench_source_tab()).init();
    }
    async bind_model_picker()        { return (await import("./evt/bind_model_picker.js")).bind_model_picker(); }
    async filter_bench_source_list() { return (await import("./evt/filter_bench_source_list.js")).filter_bench_source_list(); }
    async filter_model_list()        { return (await import("./evt/filter_model_list.js")).filter_model_list(); }
    async refresh_model_list()       { return (await import("./evt/refresh_model_list.js")).refresh_model_list(); }
    async switch_bench_source_item() { return (await import("./evt/switch_bench_source_item.js")).switch_bench_source_item(); }
    async switch_bench_source_tab()  { return (await import("./evt/switch_bench_source_tab.js")).switch_bench_source_tab(); }
}

// act（動作——開單叫工作）
class _bench_modal_act_bridge {
    constructor() {}
    init() {

    }
    async model_picker()             { return (await import("./act/model_picker.js")).model_picker(); }
    async modal_action()             { return (await import("./act/modal_action.js")).modal_action(); }
    async render_model_list()        { return (await import("./act/render_model_list.js")).render_model_list(); }
    async switch_bench_source_item() { return (await import("./act/switch_bench_source_item.js")).switch_bench_source_item(); }
    async switch_bench_source_tab()  { return (await import("./act/switch_bench_source_tab.js")).switch_bench_source_tab(); }
}

// req（出街工作）
class _bench_modal_req_bridge {
    constructor() {}
    init() {

    }
    async fetch_bench_source()             { return (await import("./req/fetch_bench_source.js")).fetch_bench_source(); }
    async fetch_bench_source_navigation()  { return (await import("./req/fetch_bench_source_navigation.js")).fetch_bench_source_navigation(); }
    async fetch_models()                   { return (await import("./req/fetch_models.js")).fetch_models(); }
    async redownload_bench_source_models() { return (await import("./req/redownload_bench_source_models.js")).redownload_bench_source_models(); }
}
