// jsmods/_common/_common.js —— _common 桶（無域歸屬——暫放；將來按類型重整理）
// 職責：桶內共用動作接駁（唯一入口——同各域 <域>.js 同形）
// 用途：(await registry().sc_ai_chats()._common()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class __common {
    constructor() {
        this.inited = false;
    }

    // 啟動桶（sc_ai_chats.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（_common evt 家族——分組見 bind_events）
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
__common.prototype.evt = function () { return new _common_evt_bridge(); };
__common.prototype.act = function () { return new _common_act_bridge(); };
__common.prototype.req = function () { return new _common_req_bridge(); };

export const _common = (function (instance) {
    return function () {

        return instance;
    };
})(new __common());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _common_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.coming_soon()).init();
    }
    async coming_soon() { return (await import("./evt/coming_soon.js")).coming_soon(); }
}

// act（動作——開單叫工作）
class _common_act_bridge {
    constructor() {}
    init() {

    }
    async replace_slots() { return (await import("./act/replace_slots.js")).replace_slots(); }
}

// req（出街工作）
class _common_req_bridge {
    constructor() {}
    init() {

    }
}
