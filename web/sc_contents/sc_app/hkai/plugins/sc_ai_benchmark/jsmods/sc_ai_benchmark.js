// jsmods/sc_ai_benchmark.js —— sc_ai_benchmark plugin 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_ai_benchmark()（單例）；域接駁：(await registry().sc_ai_benchmark().bench_modal()).act().<動作>() 咁叫
// 職責：啟動編排（init——bootstrap 呼叫；域召集）
// 契約：1 域（bench_modal）；域動作住域 <域>.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_ai_benchmark {
    constructor() {
        this.inited = false;
        this.hooks = {};
    }

    // 啟動 plugin（bootstrap 取例項後呼叫——域召集；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（bench_modal 域——事件綁定）
        const bench_modal_instance = await this.bench_modal();
        await bench_modal_instance.init();

        return this;
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_benchmark.prototype.bench_modal = async function () { return (await import("./bench_modal/bench_modal.js")).bench_modal(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_benchmark.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_ai_benchmark = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_benchmark());
