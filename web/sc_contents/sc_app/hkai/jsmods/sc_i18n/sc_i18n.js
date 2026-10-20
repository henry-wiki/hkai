// jsmods/sc_i18n/sc_i18n.js —— i18n 字典模組（shared module——供各 plugin 使用）
// 職責：向 server 取語系字典（GET /request/hkai/i18n/，與 PHP 共用同一份 JSON）→ get(key) 查字

class _sc_i18n {
    constructor() {
        this.options = {};
        this.options.lang_url = "/request/hkai/i18n/";

        this.lang_data = {};
    }

    // bootstrap 自動呼叫：取字典一次（async——尚未取回前 get() 回 key 本身）
    init() {
        this.fetch_lang_data();
    }

    async fetch_lang_data() {
        try {
            const response = await fetch(this.options.lang_url);
            const result = await response.json();
            if (!response.ok) {
                console.error("i18n fetch failed:", result.error);
                return;
            }
            this.lang_data = result.lang_data;
        } catch (fetch_error) {
            // 網路／解析失敗 → console.error（native 錯誤訊息）
            console.error("i18n fetch failed:", fetch_error.message);
        }
    }

    // 查字（未命中／尚未取回 → 回 key 本身）
    get(key) {
        if (key in this.lang_data) {
            return this.lang_data[key];
        }

        return key;
    }
}

export const sc_i18n = (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_i18n());
