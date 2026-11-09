// plugins/sc_stream/jsmods/sc_state_stream.js —— stream plugin 前端根入口（唯一入口）
// 用途：registry().sc_state_stream().stream().act().<動作>()（狀態事件套用層）
// 職責：接收 state_stream／prompt_stream 事件 → 交 act_prompt_updater 套用（草稿還原）
// 契約：1 域（stream）；動作住 stream/stream.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "../../../jsmods/_common/_domain_hooks.js";

class _sc_state_stream {
    constructor() {
        this.hooks = {};
    }
}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_state_stream.prototype.stream = async function () { return (await import("./stream/stream.js")).stream(); };

// 連接（hook 機制掛上 root——唯一位置）
Object.assign(_sc_state_stream.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_state_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_state_stream());
