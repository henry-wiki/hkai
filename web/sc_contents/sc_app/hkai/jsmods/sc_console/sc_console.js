// jsmods/sc_console/sc_console.js —— 全站 console 統一入口（框架）
// 用法：
//   sc_console.log(...)                 → 無 scope
//   sc_console.warn(...)                → 無 scope
//   sc_console.error(...)               → 無 scope
//   sc_console("scope_a", "scope_b").log(...)   → 帶 scope
//   sc_console("scope_a", "scope_b").warn(...)
// 全域控制（喺 console 即時改，毋須 reload）：
//   window.sc_console_settings.enabled      true／false（總開關）
//   window.sc_console_settings.show_error   true／false（error 另計）
//   window.sc_console_settings.scopes       []＝全部；["polling"]＝只顯示含 polling 嘅 scope
//   window.sc_console_settings.timestamp    true／false（加 ISO 時間）
// 上游：任何 jsmods／plugin（直接 import）
// 下游：原生 console.log／warn／error（唯一出口）

// 全域設定（window 上——可即時改）
const SC_CONSOLE_DEFAULTS = {
    enabled: true,
    show_error: true,
    scopes: [],
    timestamp: true,
};

const SC_CONSOLE_SETTINGS = (function () {
    // 判定：window 未有設定 → 用預設
    if (typeof window.sc_console_settings === "undefined") {
        window.sc_console_settings = {
            enabled: SC_CONSOLE_DEFAULTS.enabled,
            show_error: SC_CONSOLE_DEFAULTS.show_error,
            scopes: SC_CONSOLE_DEFAULTS.scopes.slice(),
            timestamp: SC_CONSOLE_DEFAULTS.timestamp,
        };
    }

    return window.sc_console_settings;
})();

class _sc_console {
    constructor(scope_items) {
        this.scope_items = scope_items;
    }

    log() {
        this._write("log", arguments);
    }

    warn() {
        this._write("warn", arguments);
    }

    error() {
        this._write("error", arguments);
    }

    // 是否顯示（總開關／error 開關／scope 過濾）
    _is_visible(level_name) {
        // 判定：總開關關 → 唔顯示
        if (SC_CONSOLE_SETTINGS.enabled === false) {
            return false;
        }

        // 判定：error 而 error 開關關 → 唔顯示
        if (level_name === "error" && SC_CONSOLE_SETTINGS.show_error === false) {
            return false;
        }

        // 判定：冇 scope 過濾 → 全部顯示
        if (SC_CONSOLE_SETTINGS.scopes.length === 0) {
            return true;
        }

        const scope_text = this.scope_items.join(" ");

        // 逐個允許 scope 比對（字串包含）
        for (let scope_index = 0; scope_index < SC_CONSOLE_SETTINGS.scopes.length; scope_index++) {
            const allowed_scope = SC_CONSOLE_SETTINGS.scopes[scope_index];

            // 判定：命中 → 顯示
            if (scope_text.indexOf(allowed_scope) >= 0) {
                return true;
            }
        }

        return false;
    }

    // 砌前綴（scope 字串＋ISO 時間）
    _build_prefix() {
        const prefix_items = [];

        // 判定：有 scope → 加 [scope]
        if (this.scope_items.length > 0) {
            prefix_items.push("[" + this.scope_items.join("][") + "]");
        }

        // 判定：要時間戳 → 加 ISO
        if (SC_CONSOLE_SETTINGS.timestamp === true) {
            prefix_items.push(new Date().toISOString());
        }

        return prefix_items.join(" ");
    }

    // 真正輸出（唯一出口）
    _write(level_name, args_object) {
        // 判定：唔顯示 → 零動作
        if (this._is_visible(level_name) === false) {
            return;
        }

        const prefix_text = this._build_prefix();
        const args_items = Array.prototype.slice.call(args_object);

        // 判定：有前綴 → 放最前
        if (prefix_text !== "") {
            args_items.unshift(prefix_text);
        }

        console[level_name].apply(console, args_items);
    }
}

// 對外：可呼叫（帶 scope）＋直接方法（無 scope）
export const sc_console = (function () {
    const root_instance = new _sc_console([]);

    const callable = function () {
        const scope_items = Array.prototype.slice.call(arguments);

        return new _sc_console(scope_items);
    };

    callable.log = function () {
        root_instance.log.apply(root_instance, arguments);
    };

    callable.warn = function () {
        root_instance.warn.apply(root_instance, arguments);
    };

    callable.error = function () {
        root_instance.error.apply(root_instance, arguments);
    };

    // 設定物件（即時改）
    callable.settings = SC_CONSOLE_SETTINGS;

    return callable;
})();

// 全域掛載（console 直接可用：window.sc_console("scope").log(...)）
window.sc_console = sc_console;
