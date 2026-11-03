// jsmods/sc_state_watch/sc_state_watch.js —— 狀態看守根入口（唯一入口——出入口掛喺 prototype；對齊 sc_modal 形）
// 用途：registry().sc_state_watch()（單例）；域接駁：registry().sc_state_watch().state_watch().act().write() 咁叫
// 職責：① 啟動編排（init——域召集）② 對外域橋（state_watch）
// 契約：1 域（state_watch）；域動作住 state_watch/state_watch.js 檔尾（bridge class——每條自載）

import { state_watch } from "./state_watch/state_watch.js";

class _sc_state_watch {
    constructor() {
        this.inited = false;
    }

    // 啟動（域召集——觀察者啟動；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（state_watch 域——啟動觀察者）
        const state_watch_domain = this.state_watch();

        // 頁面 render 嘅初始值（唯一一次——之後只由 SSE 變化更新）
        const store_instance = await state_watch_domain.store();
        store_instance.hydrate_from_dom();

        await state_watch_domain.init();

        return this;
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_state_watch.prototype.state_watch = function () { return state_watch(); };

export const sc_state_watch = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_state_watch());
