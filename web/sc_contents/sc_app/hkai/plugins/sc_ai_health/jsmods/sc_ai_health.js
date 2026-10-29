// plugins/sc_ai_health/jsmods/sc_ai_health.js —— health plugin 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_ai_health()（單例）；域接駁：(await registry().sc_ai_health().health()).act().<動作>() 咁叫
// 職責：① 啟動編排（init——bootstrap 呼叫；域召集＋抓取引擎）② 多頁並存＝各自跑（唔睇分頁可見性；SSE 一直開）
// 契約：1 域（health）；域動作住 health/health.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_ai_health {
    constructor() {
        this.inited = false;
        this.hooks = {};
    }

    // 啟動 plugin（bootstrap 取例項後呼叫——域召集＋抓取引擎；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（health 域——事件綁定）
        const health_instance = await this.health();
        await health_instance.init();

        // 橋接駁（health 域 act 橋——抓取引擎；啟動即開）
        const act_fetch_health_instance = await health_instance.act().act_fetch_health();
        act_fetch_health_instance.init();

        act_fetch_health_instance.start();

        return this;
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_health.prototype.health = async function () { return (await import("./health/health.js")).health(); };
_sc_ai_health.prototype.stream = async function () { return (await import("./stream/stream.js")).stream(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_health.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_ai_health = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_health());
