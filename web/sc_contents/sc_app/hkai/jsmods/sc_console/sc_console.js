// jsmods/sc_console/sc_console.js —— 全站 console 統一入口（框架）
// 用法：
//   sc_console.log(...)                 → 無 scope
//   sc_console.warn(...)                → 無 scope
//   sc_console.error(...)               → 無 scope
//   sc_console("scope_a", "scope_b").log(...)   → 帶 scope
//   sc_console("scope_a", "scope_b").warn(...)
// 出廠預設：enabled=false（唔出 log）；要睇就在 console 執行 window.sc_console_settings.enabled = true
// 全域控制（喺 console 即時改，毋須 reload）：
//   window.sc_console_settings.enabled      true／false（總開關）
//   window.sc_console_settings.show_error   true／false（error 另計）
//   window.sc_console_settings.scopes       []＝全部；["polling"]＝只顯示含 polling 嘅 scope
//   window.sc_console_settings.timestamp    true／false（加 ISO 時間）
//   window.sc_console_settings.show_caller  true／false（尾隨可點擊嘅呼叫者 URL）
// 上游：任何 jsmods／plugin（直接 import）
// 下游：原生 console.log／warn／error（唯一出口）

// 全域設定（window 上——可即時改）
const SC_CONSOLE_DEFAULTS = {
    enabled: false,
    show_error: true,
    scopes: [],
    timestamp: true,
    show_caller: true,
};

const SC_CONSOLE_SETTINGS = (function () {
    // 判定：window 未有設定 → 用預設
    if (typeof window.sc_console_settings === "undefined") {
        window.sc_console_settings = {
            enabled: SC_CONSOLE_DEFAULTS.enabled,
            show_error: SC_CONSOLE_DEFAULTS.show_error,
            scopes: SC_CONSOLE_DEFAULTS.scopes.slice(),
            timestamp: SC_CONSOLE_DEFAULTS.timestamp,
            show_caller: SC_CONSOLE_DEFAULTS.show_caller,
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

    // 砌前綴（逐個 scope 一個參數＋ISO 時間——直接對應 console.log 嘅參數位）
    _build_prefix_items() {
        const prefix_items = [];

        // 逐個 scope：各自一個參數
        for (let scope_index = 0; scope_index < this.scope_items.length; scope_index++) {
            prefix_items.push("[" + this.scope_items[scope_index] + "]");
        }

        // 判定：要時間戳 → 加 ISO
        if (SC_CONSOLE_SETTINGS.timestamp === true) {
            prefix_items.push(new Date().toISOString());
        }

        return prefix_items;
    }

    // 抽呼叫者 URL（第一個唔屬於 sc_console.js 嘅 frame——Chrome 會 link 化，點擊跳 Sources）
    _build_caller_url() {
        const stack_text  = new Error().stack || "";
        const stack_lines = stack_text.split("\n");

        for (let line_index = 1; line_index < stack_lines.length; line_index++) {
            const stack_line = stack_lines[line_index];

            // 判定：框架自己嘅 frame → 跳過
            if (stack_line.indexOf("/sc_console/sc_console.js") >= 0) {
                continue;
            }

            const url_match = stack_line.match(/(https?:\/\/[^\s)]+):(\d+):(\d+)/);

            // 判定：抽唔到 URL → 下一行
            if (url_match === null) {
                continue;
            }

            return url_match[1] + ":" + url_match[2];
        }

        return "";
    }

    // 真正輸出（唯一出口）
    _write(level_name, args_object) {
        // 判定：唔顯示 → 零動作
        if (this._is_visible(level_name) === false) {
            return;
        }

        const prefix_items = this._build_prefix_items();
        const args_items = prefix_items.concat(Array.prototype.slice.call(args_object));

        // 判定：要顯示呼叫者 → 加 URL 做最後一個參數（可點擊跳 Sources）
        if (SC_CONSOLE_SETTINGS.show_caller === true) {
            const caller_url = this._build_caller_url();

            // 判定：抽到 URL → 加入
            if (caller_url !== "") {
                args_items.push(caller_url);
            }
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
