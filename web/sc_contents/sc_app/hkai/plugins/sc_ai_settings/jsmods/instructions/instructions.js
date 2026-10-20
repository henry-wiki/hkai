// jsmods/instructions/instructions.js —— instructions 域（唯一入口——出入口掛喺 prototype）
// 用途：(await (await registry().sc_ai_settings()).instructions()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _instructions {
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

        // 事件綁定（instructions evt 家族——分組見 bind_events）
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
_instructions.prototype.evt = function () { return new _instructions_evt_bridge(); };
_instructions.prototype.act = function () { return new _instructions_act_bridge(); };
_instructions.prototype.req = function () { return new _instructions_req_bridge(); };

export const instructions = (function (instance) {
    return function () {

        return instance;
    };
})(new _instructions());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _instructions_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.instructions_save()).init();
        (await this.instructions_reset()).init();
        (await this.instructions_refresh_system_env()).init();
    }
    async instructions_save()    { return (await import("./evt/instructions_save.js")).instructions_save(); }
    async instructions_reset()   { return (await import("./evt/instructions_reset.js")).instructions_reset(); }
    async instructions_refresh_system_env() { return (await import("./evt/instructions_refresh_system_env.js")).instructions_refresh_system_env(); }
}

// act（動作——開單叫工作）
class _instructions_act_bridge {
    constructor() {}
    init() {

    }
    async instructions()  { return (await import("./act/instructions.js")).instructions(); }
}

// req（出街工作）
class _instructions_req_bridge {
    constructor() {}
    init() {

    }
}
