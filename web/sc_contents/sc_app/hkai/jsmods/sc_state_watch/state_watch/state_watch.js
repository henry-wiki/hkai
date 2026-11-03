// jsmods/sc_state_watch/state_watch/state_watch.js —— 狀態看守域（唯一入口——出入口掛喺 prototype）
// 用途：registry().sc_state_watch().state_watch().act().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act 全部接喺度；每條自載）
// 界線：只寫自己容器（.sc_state_watch），唔碰任何外部節點

class _state_watch {
    constructor() {
        this.inited = false;

        // 上一輪 polling 名單（單位消失要歸零——防數值卡住）
        this.polling_units = { project: [], chat: [] };
    }

    // 啟動域（sc_state_watch.init 召集——觀察者啟動；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 根容器＋五個 target 空殼（開頁即建——即使零變化都要有）
        const dom_instance = await this.dom();
        const contract_module = await this.contract();

        dom_instance.ensure_root();

        // 逐個契約 target 建空殼
        for (const target_name in contract_module.STATE_WATCH_CONTRACT.targets) {
            dom_instance.ensure_target(target_name);
        }

        // 觀察者啟動
        const observer_instance = await this.observer();
        observer_instance.start();

        return this;
    }
}

// 連接（出入口掛上 prototype）
_state_watch.prototype.act      = function () { return new _state_watch_act_bridge(); };
_state_watch.prototype.store    = async function () { return (await import("../store/store.js")).store(); };
_state_watch.prototype.dom      = async function () { return (await import("../dom/dom.js")).dom(); };
_state_watch.prototype.observer = async function () { return (await import("../observer/observer.js")).observer(); };
_state_watch.prototype.contract = async function () { return await import("../contract/contract.js"); };

export const state_watch = (function (instance) {
    return function () {

        return instance;
    };
})(new _state_watch());

// ── 動作表（bridge——接駁 act 目錄嘅 function；每條自載）──

// act（動作——開單叫工作）
class _state_watch_act_bridge {
    constructor() {}

    // 寫入（回實例→再叫 write）
    async write()                      { return (await import("./act/write.js")).write(); }

    // 讀取（回實例→再叫 read）
    async read()                       { return (await import("./act/read.js")).read(); }

    // polling 快照（回實例→再叫 apply_polling）
    async apply_polling()              { return (await import("./act/apply_polling.js")).apply_polling(); }

    // 訂閱（回實例→再叫 subscribe）
    async subscribe()                  { return (await import("./act/subscribe.js")).subscribe(); }
}
