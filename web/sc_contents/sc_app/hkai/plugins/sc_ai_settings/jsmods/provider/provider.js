// jsmods/provider/provider.js —— provider 域（唯一入口——出入口掛喺 prototype）
// 用途：(await (await registry().sc_ai_settings()).provider()).act().provider() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt 接喺度；每條自載）

class _provider {
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

        // 事件綁定（provider evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
    }
}

// 連接（出入口掛上 prototype）
_provider.prototype.act = function () { return new _provider_act_bridge(); };
_provider.prototype.evt = function () { return new _provider_evt_bridge(); };

export const provider = (function (instance) {
    return function () {

        return instance;
    };
})(new _provider());

// ── 動作表（bridge——接駁 act／evt 目錄嘅 function；每條自載）──

// evt（事件入口）
class _provider_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.provider_save()).init();
        (await this.provider_reset()).init();
        (await this.provider_cancel()).init();
    }
    async provider_save() { return (await import("./evt/provider_save.js")).provider_save(); }
    async provider_reset() { return (await import("./evt/provider_reset.js")).provider_reset(); }
    async provider_cancel() { return (await import("./evt/provider_cancel.js")).provider_cancel(); }
}

// act（動作——開單叫工作）
class _provider_act_bridge {
    constructor() {}
    init() {

    }
    async provider()  { return (await import("./act/provider.js")).provider(); }
}
