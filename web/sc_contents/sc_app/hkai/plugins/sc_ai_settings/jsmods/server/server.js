// jsmods/server/server.js —— server 域（唯一入口——出入口掛喺 prototype）
// 用途：(await (await registry().sc_ai_settings()).server()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _server {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_settings.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（server evt 家族——分組見 bind_events）
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
_server.prototype.evt = function () { return new _server_evt_bridge(); };
_server.prototype.act = function () { return new _server_act_bridge(); };
_server.prototype.req = function () { return new _server_req_bridge(); };

export const server = (function (instance) {
    return function () {

        return instance;
    };
})(new _server());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _server_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.server_start()).init();
        (await this.server_restart()).init();
        (await this.server_stop()).init();
        (await this.auto_assign_port()).init();
    }
    async server_start()   { return (await import("./evt/server_start.js")).server_start(); }
    async server_restart() { return (await import("./evt/server_restart.js")).server_restart(); }
    async server_stop()    { return (await import("./evt/server_stop.js")).server_stop(); }
    async auto_assign_port() { return (await import("./evt/auto_assign_port.js")).auto_assign_port(); }
}

// act（動作——開單叫工作）
class _server_act_bridge {
    constructor() {}
    init() {

    }
    async server()  { return (await import("./act/server.js")).server(); }
}

// req（出街工作）
class _server_req_bridge {
    constructor() {}
    init() {

    }
}
