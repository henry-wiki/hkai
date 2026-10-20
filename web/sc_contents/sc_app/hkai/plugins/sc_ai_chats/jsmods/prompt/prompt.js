// jsmods/prompt/prompt.js —— prompt 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_chats().prompt()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req／obs 全部接喺度；每條自載）

class _prompt {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_chats.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（prompt evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 讀 model_info 容器全欄位（data-model_field 為 key——容器加新欄位自動帶上，零 JS 改動）
    get_model_info_data() {
        const model_info_data = {};
        for (const field_input of jQuery("[data-is='model_info']").find("input[data-model_field]")) {
            model_info_data[field_input.dataset.model_field] = field_input.value;
        }
        return model_info_data;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_prompt.prototype.evt = function () { return new _prompt_evt_bridge(); };
_prompt.prototype.act = function () { return new _prompt_act_bridge(); };
_prompt.prototype.req = function () { return new _prompt_req_bridge(); };
_prompt.prototype.obs = function () { return new _prompt_obs_bridge(); };

export const prompt = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt());

// ── 動作表（bridge——接駁 act／evt／req／obs 四個目錄嘅 function；每條自載）──

// evt（事件入口）
class _prompt_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.daemon_engine_switch()).init();
        (await this.prompt_draft_save()).init();
        (await this.prompt_enter_send()).init();
        (await this.prompt_gen_random()).init();
        (await this.prompt_input()).init();
        (await this.prompt_send()).init();
        (await this.prompt_stop()).init();
    }
    async daemon_engine_switch() { return (await import("./evt/daemon_engine_switch.js")).daemon_engine_switch(); }
    async prompt_draft_save() { return (await import("./evt/prompt_draft_save.js")).prompt_draft_save(); }
    async prompt_enter_send() { return (await import("./evt/prompt_enter_send.js")).prompt_enter_send(); }
    async prompt_gen_random() { return (await import("./evt/prompt_gen_random.js")).prompt_gen_random(); }
    async prompt_input()      { return (await import("./evt/prompt_input.js")).prompt_input(); }
    async prompt_send()       { return (await import("./evt/prompt_send.js")).prompt_send(); }
    async prompt_stop()       { return (await import("./evt/prompt_stop.js")).prompt_stop(); }
}

// act（動作——開單叫工作）
class _prompt_act_bridge {
    constructor() {}
    init() {

    }
    async prompt_draft_save()        { return (await import("./act/prompt_draft_save.js")).prompt_draft_save(); }
    async prompt_fetch()           { return (await import("./act/prompt_fetch.js")).prompt_fetch(); }
    async prompt_send()               { return (await import("./act/prompt_send.js")).prompt_send(); }
    async prompt_send_button_toggle() { return (await import("./act/prompt_send_button_toggle.js")).prompt_send_button_toggle(); }
    async prompt_stop()               { return (await import("./act/prompt_stop.js")).prompt_stop(); }
}

// req（出街工作）
class _prompt_req_bridge {
    constructor() {}
    init() {

    }
    async daemon_engine_switch() { return (await import("./req/daemon_engine_switch.js")).daemon_engine_switch(); }
    async prompt_fetch() { return (await import("./req/prompt_fetch.js")).prompt_fetch(); }
    async prompt_send()  { return (await import("./req/prompt_send.js")).prompt_send(); }
    async prompt_stop()  { return (await import("./req/prompt_stop.js")).prompt_stop(); }
}

// obs（DOM 觀察器——變化派 hook 信號；唔判斷）
class _prompt_obs_bridge {
    constructor() {}
    async prompt_input_height_observer() { return (await import("./obs/prompt_input_height_observer.js")).prompt_input_height_observer(); }
}
