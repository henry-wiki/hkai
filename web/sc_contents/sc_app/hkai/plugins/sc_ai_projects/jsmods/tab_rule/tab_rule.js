// jsmods/tab_rule/tab_rule.js —— tab_rule 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_projects().tab_rule()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _tab_rule {
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

        // 事件綁定（tab_rule evt 家族——分組見 bind_events）
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

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_tab_rule.prototype.evt = function () { return new _tab_rule_evt_bridge(); };
_tab_rule.prototype.act = function () { return new _tab_rule_act_bridge(); };
_tab_rule.prototype.req = function () { return new _tab_rule_req_bridge(); };

export const tab_rule = (function (instance) {
    return function () {

        return instance;
    };
})(new _tab_rule());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載） ──

// evt（事件入口）
class _tab_rule_evt_bridge {
    constructor() {}

    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.save_tab_rule()).init();
        (await this.reset_tab_rule()).init();
    }

    async save_tab_rule()  { return (await import("./evt/save_tab_rule.js")).save_tab_rule(); }
    async reset_tab_rule() { return (await import("./evt/reset_tab_rule.js")).reset_tab_rule(); }
}

// act（動作——開單叫工作）
class _tab_rule_act_bridge {
    constructor() {}

    init() {

    }

    async rule_editor()    { return (await import("./act/rule_editor.js")).rule_editor(); }
    async save_tab_rule()  { return (await import("./act/save_tab_rule.js")).save_tab_rule(); }
    async reset_tab_rule() { return (await import("./act/reset_tab_rule.js")).reset_tab_rule(); }
}

// req（出街工作）
class _tab_rule_req_bridge {
    constructor() {}

    init() {

    }

    async save_tab_rule()  { return (await import("./req/save_tab_rule.js")).save_tab_rule(); }
    async reset_tab_rule() { return (await import("./req/reset_tab_rule.js")).reset_tab_rule(); }
}
