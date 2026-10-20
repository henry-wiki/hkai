// jsmods/queue/queue.js —— queue 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_chats().queue()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _queue {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_chats.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（queue evt 家族——分組見 bind_events）
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
_queue.prototype.evt = function () { return new _queue_evt_bridge(); };
_queue.prototype.act = function () { return new _queue_act_bridge(); };
_queue.prototype.req = function () { return new _queue_req_bridge(); };

export const queue = (function (instance) {
    return function () {

        return instance;
    };
})(new _queue());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _queue_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.queue_cancel()).init();
        (await this.queue_clear()).init();
    }
    async queue_cancel() { return (await import("./evt/queue_cancel.js")).queue_cancel(); }
    async queue_clear()  { return (await import("./evt/queue_clear.js")).queue_clear(); }
}

// act（動作——開單叫工作）
class _queue_act_bridge {
    constructor() {}
    init() {

    }
    async queue_dispatch() { return (await import("./act/queue_dispatch.js")).queue_dispatch(); }
    async queue_list()     { return (await import("./act/queue_list.js")).queue_list(); }
}

// req（出街工作）
class _queue_req_bridge {
    constructor() {}
    init() {

    }
    async queue_cancel()   { return (await import("./req/queue_cancel.js")).queue_cancel(); }
    async queue_clear()    { return (await import("./req/queue_clear.js")).queue_clear(); }
    async queue_dispatch() { return (await import("./req/queue_dispatch.js")).queue_dispatch(); }
    async queue_list()     { return (await import("./req/queue_list.js")).queue_list(); }
}
