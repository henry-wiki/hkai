// jsmods/modal/modal.js —— modal 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_modal().modal()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _modal {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_modal.init 召集——事件綁定；重複呼叫只行一次）
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
})(new _modal());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口——本域零 DOM 事件入口；框架存在）
class _modal_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
    }
}

// act（動作——開單叫工作）
class _modal_act_bridge {
    constructor() {}
    init() {

    }
    async create_modal()        { return (await import("./act/create_modal.js")).create_modal(); }
    async render_modal()        { return (await import("./act/render_modal.js")).render_modal(); }
    async fill_modal_content()  { return (await import("./act/fill_modal_content.js")).fill_modal_content(); }
    async bind_modal_events()   { return (await import("./act/bind_modal_events.js")).bind_modal_events(); }
    async get_modal_template()  { return (await import("./act/get_modal_template.js")).get_modal_template(); }
}

// req（出街工作——本域零 request；框架存在）
class _modal_req_bridge {
    constructor() {}
    init() {

    }
}
