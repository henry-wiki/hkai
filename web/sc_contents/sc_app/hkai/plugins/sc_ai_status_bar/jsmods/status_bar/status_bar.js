// jsmods/status_bar/status_bar.js —— status_bar 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_status_bar().status_bar()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _status_bar {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_status_bar.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（status_bar evt 家族——分組見 bind_events）
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
_status_bar.prototype.evt = function () { return new _status_bar_evt_bridge(); };
_status_bar.prototype.act = function () { return new _status_bar_act_bridge(); };
_status_bar.prototype.req = function () { return new _status_bar_req_bridge(); };
_status_bar.prototype.obs = function () { return new _status_bar_obs_bridge(); };

export const status_bar = (function (instance) {
    return function () {

        return instance;
    };
})(new _status_bar());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _status_bar_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.changed_message_state()).init();
        (await this.changed_chat_options()).init();
        (await this.clicked_status_item()).init();
        (await this.clicked_about_copyright()).init();
    }
    async changed_message_state()   { return (await import("./evt/changed_message_state.js")).changed_message_state(); }
    async changed_chat_options()    { return (await import("./evt/changed_chat_options.js")).changed_chat_options(); }
    async clicked_status_item()     { return (await import("./evt/clicked_status_item.js")).clicked_status_item(); }
    async clicked_about_copyright() { return (await import("./evt/clicked_about_copyright.js")).clicked_about_copyright(); }
}

// act（動作——開單叫工作）
class _status_bar_act_bridge {
    constructor() {}
    init() {

    }
    async sync_status_items()       { return (await import("./act/sync_status_items.js")).sync_status_items(); }
    async tick_clock()              { return (await import("./act/tick_clock.js")).tick_clock(); }
    async refresh_git_item()        { return (await import("./act/refresh_git_item.js")).refresh_git_item(); }
    async status_item_render()      { return (await import("./act/status_item_render.js")).status_item_render(); }
    async conn_status_item()        { return (await import("./act/conn_status_item.js")).conn_status_item(); }
    async git_status_item()         { return (await import("./act/git_status_item.js")).git_status_item(); }
    async open_project_git_modal()  { return (await import("./act/open_project_git_modal.js")).open_project_git_modal(); }
    async project_fetch()           { return (await import("./act/project_fetch.js")).project_fetch(); }
    async chat_fetch()              { return (await import("./act/chat_fetch.js")).chat_fetch(); }
    async workspace_fetch()         { return (await import("./act/workspace_fetch.js")).workspace_fetch(); }
}

// req（出街工作）
class _status_bar_req_bridge {
    constructor() {}
    init() {

    }
    async project_status_bar_fetch()   { return (await import("./req/project_status_bar_fetch.js")).project_status_bar_fetch(); }
    async chat_status_bar_fetch()      { return (await import("./req/chat_status_bar_fetch.js")).chat_status_bar_fetch(); }
    async workspace_status_bar_fetch() { return (await import("./req/workspace_status_bar_fetch.js")).workspace_status_bar_fetch(); }
}

// obs（DOM 觀察器——變化派 hook 信號；唔判斷）
class _status_bar_obs_bridge {
    constructor() {}
    async url_changed_observer() { return (await import("./obs/url_changed_observer.js")).url_changed_observer(); }
}
