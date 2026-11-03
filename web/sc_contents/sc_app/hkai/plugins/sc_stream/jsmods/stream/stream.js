// jsmods/stream/stream.js —— stream 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_state_stream().stream()).act().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _stream {
    constructor() {
        this.inited = false;
    }

    // 啟動域（重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        await this.init_bridges();

        return this;
    }

    // 開域召集（橋各自 init）
    async init_bridges() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_stream.prototype.evt = function () { return new _stream_evt_bridge(); };
_stream.prototype.act = function () { return new _stream_act_bridge(); };
_stream.prototype.req = function () { return new _stream_req_bridge(); };

export const stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _stream());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _stream_evt_bridge {
    constructor() {}
    // 綁定召集（現時無 evt 動作——預留；新增 evt 時在此 init）
    async init() {

    }
}

// act（動作——開單叫工作）
class _stream_act_bridge {
    constructor() {}
    // 動作橋 init（動作全為 on-demand 自載——預留）
    init() {

    }
    async act_project_updater() { return (await import("./act/act_project_updater.js")).act_project_updater(); }
    async act_prompt_updater()  { return (await import("./act/act_prompt_updater.js")).act_prompt_updater(); }
    async act_chat_updater()    { return (await import("./act/act_chat_updater.js")).act_chat_updater(); }
    async act_message_updater() { return (await import("./act/act_message_updater.js")).act_message_updater(); }
    async act_git_updater()     { return (await import("./act/act_git_updater.js")).act_git_updater(); }
    async act_conn_updater()    { return (await import("./act/act_conn_updater.js")).act_conn_updater(); }
}

// req（出街工作）
class _stream_req_bridge {
    constructor() {}
    // 出街橋 init（stream 現時無 req 動作——預留）
    init() {

    }
}
