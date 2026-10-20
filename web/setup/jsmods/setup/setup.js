// jsmods/setup/setup.js —— setup 域（唯一入口——出入口掛喺 prototype）
// 用途：頁面載入後 setup().init()——setup().evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——evt／act／req 全部接喺度；每條自載）

class _setup {
    constructor() {
        this.inited = false;
    }

    // 啟動域（頁面入口召集——事件綁定＋開場逐項查；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（evt 家族——分組見 bind_events）
        await this.bind_events();

        // 開場逐項查（本格 init 項——唔等：背景逐項查、漸進換卡）
        const refresh_check_item_instance = await this.act().refresh_check_item();

        refresh_check_item_instance.start_batch_check();

        return this;
    }

    // 開域召集（evt 橋 init——事件綁定家族）
    async bind_events() {
        await this.evt().init();
    }

    // ── 共用讀寫（各組經域例項存取）──

    // 取 modal 根（事件綁定錨——.setup_modal）
    get_modal_element() {
        return jQuery(".setup_modal");
    }

    // 取目前格 id（.setup_section.is-active——active 格常駐）
    get_current_section_id() {
        return jQuery(".setup_section.is-active").attr("data-setup_section");
    }

    // 取格元素（.setup_section[data-setup_section=id]）
    get_section_element(section_id) {
        return jQuery(".setup_section[data-setup_section='" + section_id + "']");
    }
}

// 連接（出入口掛上 prototype）
_setup.prototype.evt = function () { return new _setup_evt_bridge(); };
_setup.prototype.act = function () { return new _setup_act_bridge(); };
_setup.prototype.req = function () { return new _setup_req_bridge(); };

export const setup = (function (instance) {
    return function () {

        return instance;
    };
})(new _setup());

// ── 動作表（bridge——接駁 evt／act／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _setup_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.switch_section()).init();
        (await this.refresh_install_item()).init();
        (await this.switch_language()).init();
        (await this.close_setup()).init();
    }
    async switch_section()       { return (await import("./evt/switch_section.js")).switch_section(); }
    async refresh_install_item() { return (await import("./evt/refresh_install_item.js")).refresh_install_item(); }
    async switch_language()      { return (await import("./evt/switch_language.js")).switch_language(); }
    async close_setup()          { return (await import("./evt/close_setup.js")).close_setup(); }
}

// act（動作——開單叫工作）
class _setup_act_bridge {
    constructor() {}
    async switch_section()          { return (await import("./act/switch_section.js")).switch_section(); }
    async refresh_setup_process()   { return (await import("./act/refresh_setup_process.js")).refresh_setup_process(); }
    async refresh_check_item()      { return (await import("./act/refresh_check_item.js")).refresh_check_item(); }
    async refresh_install_item()    { return (await import("./act/refresh_install_item.js")).refresh_install_item(); }
    async switch_language()         { return (await import("./act/switch_language.js")).switch_language(); }
    async close_setup()             { return (await import("./act/close_setup.js")).close_setup(); }
}

// req（出街工作）
class _setup_req_bridge {
    constructor() {}
    async switch_section()      { return (await import("./req/switch_section.js")).switch_section(); }
    async start_single_check()  { return (await import("./req/start_single_check.js")).start_single_check(); }
    async start_single_install() { return (await import("./req/start_single_install.js")).start_single_install(); }
    async switch_language()     { return (await import("./req/switch_language.js")).switch_language(); }
    async record_section()      { return (await import("./req/record_section.js")).record_section(); }
}

