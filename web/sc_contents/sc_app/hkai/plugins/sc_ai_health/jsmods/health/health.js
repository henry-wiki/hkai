// jsmods/health/health.js —— health 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_health().health()).evt()／act()／req()／obs().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——evt／act／req／obs 全部接喺度；每條自載）

class _health {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_health.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（health evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
        await this.obs().init();
    }
}

// 連接（出入口掛上 prototype）
_health.prototype.evt = function () { return new _health_evt_bridge(); };
_health.prototype.act = function () { return new _health_act_bridge(); };
_health.prototype.req = function () { return new _health_req_bridge(); };
_health.prototype.obs = function () { return new _health_obs_bridge(); };

export const health = (function (instance) {
    return function () {

        return instance;
    };
})(new _health());

// ── 動作表（bridge——接駁 evt／act／req／obs 四個目錄嘅 function；每條自載）──

// evt（事件入口——本域零 DOM 事件；框架存在）
class _health_evt_bridge {
    constructor() {}
    // 綁定召集（無條目——空）
    async init() {

    }
}

// act（動作——開單叫工作）
class _health_act_bridge {
    constructor() {}
    init() {

    }
    async fetch_health()              { return (await import("./act/fetch_health.js")).fetch_health(); }
}

// req（出街工作）
class _health_req_bridge {
    constructor() {}
    init() {

    }
    async fetch_health() { return (await import("./req/fetch_health.js")).fetch_health(); }
}

// obs（觀察——只綁定＋派信號）
class _health_obs_bridge {
    constructor() {}
    init() {

    }
    async visibility_changed_observer() { return (await import("./obs/visibility_changed_observer.js")).visibility_changed_observer(); }
}
