// jsmods/chat_option/chat_option.js —— chat_option 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_chats().chat_option()).evt()／act()／obs()／req().<動作>() 咁叫（F12 逐條跳得到）
// 職責：選取落地單一提交點——model_info 觀察器見真變 → submit_chat_options（chat_mode＋model 兩種變化都經呢度）
// 契約：動作表住本檔尾（bridge class——act／evt／obs／req 全部接喺度；每條自載）

class _chat_option {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_chats.init 召集——事件綁定＋觀察器；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 開域召集（橋各自 init）
        await this.bind_events();

        return this;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.obs().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_chat_option.prototype.evt = function () { return new _chat_option_evt_bridge(); };
_chat_option.prototype.act = function () { return new _chat_option_act_bridge(); };
_chat_option.prototype.obs = function () { return new _chat_option_obs_bridge(); };
_chat_option.prototype.req = function () { return new _chat_option_req_bridge(); };

export const chat_option = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_option());

// ── 動作表（bridge——接駁 act／evt／obs／req 四個目錄嘅 function；每條自載）──

// evt（事件入口）
class _chat_option_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.chat_mode()).init();
    }
    async chat_mode()  { return (await import("./evt/chat_mode.js")).chat_mode(); }
}

// act（動作——開單叫工作）
class _chat_option_act_bridge {
    constructor() {}
    init() {

    }
    async submit_chat_options()       { return (await import("./act/submit_chat_options.js")).submit_chat_options(); }
    async refresh_chat_mode_active()  { return (await import("./act/refresh_chat_mode_active.js")).refresh_chat_mode_active(); }
}

// obs（觀察器——變化 → 交動作）
class _chat_option_obs_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.model_info_observer()).init();
    }
    async model_info_observer()  { return (await import("./obs/model_info_observer.js")).model_info_observer(); }
}

// req（出街工作）
class _chat_option_req_bridge {
    constructor() {}
    init() {

    }
}
