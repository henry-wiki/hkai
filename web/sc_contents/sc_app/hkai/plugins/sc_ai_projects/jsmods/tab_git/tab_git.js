// jsmods/tab_git/tab_git.js —— tab_git 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_projects().tab_git()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _tab_git {
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

        // 事件綁定（tab_git evt 家族——分組見 bind_events）
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
_tab_git.prototype.evt = function () { return new _tab_git_evt_bridge(); };
_tab_git.prototype.act = function () { return new _tab_git_act_bridge(); };
_tab_git.prototype.req = function () { return new _tab_git_req_bridge(); };

export const tab_git = (function (instance) {
    return function () {

        return instance;
    };
}) (new _tab_git());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _tab_git_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.git_init()).init();
        (await this.git_stage_all()).init();
        (await this.git_commit()).init();
    }
    async git_init()      { return (await import("./evt/git_init.js")).git_init(); }
    async git_stage_all() { return (await import("./evt/git_stage_all.js")).git_stage_all(); }
    async git_commit()    { return (await import("./evt/git_commit.js")).git_commit(); }
}

// act（動作——開單叫工作）
class _tab_git_act_bridge {
    constructor() {}
    init() {

    }
    async git_init()      { return (await import("./act/git_init.js")).git_init(); }
    async git_stage_all() { return (await import("./act/git_stage_all.js")).git_stage_all(); }
    async git_commit()    { return (await import("./act/git_commit.js")).git_commit(); }
}

// req（出街工作）
class _tab_git_req_bridge {
    constructor() {}
    init() {

    }
    async git_init()      { return (await import("./req/git_init.js")).git_init(); }
    async git_status()    { return (await import("./req/git_status.js")).git_status(); }
    async git_stage_all() { return (await import("./req/git_stage_all.js")).git_stage_all(); }
    async git_commit()    { return (await import("./req/git_commit.js")).git_commit(); }
}
