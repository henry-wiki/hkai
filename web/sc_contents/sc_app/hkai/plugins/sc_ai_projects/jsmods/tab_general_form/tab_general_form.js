// jsmods/tab_general_form/tab_general_form.js —— tab_general_form 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_projects().tab_general_form()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _tab_general_form {
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

        // 事件綁定（tab_general_form evt 家族——分組見 bind_events）
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
_tab_general_form.prototype.evt = function () { return new _tab_general_form_evt_bridge(); };
_tab_general_form.prototype.act = function () { return new _tab_general_form_act_bridge(); };
_tab_general_form.prototype.req = function () { return new _tab_general_form_req_bridge(); };

export const tab_general_form = (function (instance) {
    return function () {

        return instance;
    };
}) (new _tab_general_form());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _tab_general_form_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.project_add()).init();
        (await this.project_edit()).init();
        (await this.project_archive()).init();
        (await this.project_delete()).init();
    }
    async project_add()      { return (await import("./evt/project_add.js")).project_add(); }
    async project_edit()     { return (await import("./evt/project_edit.js")).project_edit(); }
    async project_archive()  { return (await import("./evt/project_archive.js")).project_archive(); }
    async project_delete()   { return (await import("./evt/project_delete.js")).project_delete(); }
}

// act（動作——開單叫工作）
class _tab_general_form_act_bridge {
    constructor() {}
    init() {

    }
    async project_add()          { return (await import("./act/project_add.js")).project_add(); }
    async project_edit()         { return (await import("./act/project_edit.js")).project_edit(); }
    async project_archive()      { return (await import("./act/project_archive.js")).project_archive(); }
    async project_delete()       { return (await import("./act/project_delete.js")).project_delete(); }
    async verify_project_data()  { return (await import("./act/verify_project_data.js")).verify_project_data(); }
}

// req（出街工作）
class _tab_general_form_req_bridge {
    constructor() {}
    init() {

    }
    async project_add()          { return (await import("./req/project_add.js")).project_add(); }
    async project_edit()         { return (await import("./req/project_edit.js")).project_edit(); }
    async project_archive()      { return (await import("./req/project_archive.js")).project_archive(); }
    async project_delete()       { return (await import("./req/project_delete.js")).project_delete(); }
    async verify_project_data()  { return (await import("./req/verify_project_data.js")).verify_project_data(); }
}
