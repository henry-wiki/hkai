// jsmods/modal/modal.js —— modal 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_projects().modal()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _modal {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_projects.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（modal evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_modal.prototype.evt = function () { return new _modal_evt_bridge(); };
_modal.prototype.act = function () { return new _modal_act_bridge(); };
_modal.prototype.req = function () { return new _modal_req_bridge(); };

export const modal = (function (instance) {
    return function () {

        return instance;
    };
}) (new _modal());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _modal_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.modal_open_add()).init();
        (await this.modal_open_edit()).init();
        (await this.modal_close()).init();
        (await this.modal_refresh()).init();
        (await this.modal_tab_switch()).init();
        (await this.modal_form_init()).init();
    }
    async modal_open_add()    { return (await import("./evt/modal_open_add.js")).modal_open_add(); }
    async modal_open_edit()   { return (await import("./evt/modal_open_edit.js")).modal_open_edit(); }
    async modal_close()       { return (await import("./evt/modal_close.js")).modal_close(); }
    async modal_refresh()     { return (await import("./evt/modal_refresh.js")).modal_refresh(); }
    async modal_tab_switch()  { return (await import("./evt/modal_tab_switch.js")).modal_tab_switch(); }
    async modal_form_init()   { return (await import("./evt/modal_form_init.js")).modal_form_init(); }
}

// act（動作——開單叫工作）
class _modal_act_bridge {
    constructor() {}
    init() {

    }
    async modal_open()        { return (await import("./act/modal_open.js")).modal_open(); }
    async modal_tab_switch()  { return (await import("./act/modal_tab_switch.js")).modal_tab_switch(); }
}

// req（出街工作）
class _modal_req_bridge {
    constructor() {}
    init() {

    }
    async fetch_project_form()  { return (await import("./req/fetch_project_form.js")).fetch_project_form(); }
}
