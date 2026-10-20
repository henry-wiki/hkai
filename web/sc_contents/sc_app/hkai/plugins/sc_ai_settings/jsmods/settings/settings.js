// jsmods/settings/settings.js —— settings 域（唯一入口——出入口掛喺 prototype）
// 用途：(await (await registry().sc_ai_settings()).settings()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _settings {
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

        // 事件綁定（settings evt 家族——分組見 bind_events）
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
_settings.prototype.evt = function () { return new _settings_evt_bridge(); };
_settings.prototype.act = function () { return new _settings_act_bridge(); };
_settings.prototype.req = function () { return new _settings_req_bridge(); };

export const settings = (function (instance) {
    return function () {

        return instance;
    };
})(new _settings());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _settings_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.settings()).init();
        (await this.typed_nav_search()).init();
    }
    async settings()          { return (await import("./evt/settings.js")).settings(); }
    async typed_nav_search()  { return (await import("./evt/typed_nav_search.js")).typed_nav_search(); }
}

// act（動作——開單叫工作）
class _settings_act_bridge {
    constructor() {}
    init() {

    }
}

// req（出街工作）
class _settings_req_bridge {
    constructor() {}
    init() {

    }
    async settings_fetch()  { return (await import("./req/settings_fetch.js")).settings_fetch(); }
}
