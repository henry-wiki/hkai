// jsmods/sc_i18n/sc_i18n.js —— i18n 字典模組（shared module——供各 plugin 使用）
// 職責：向 server 取語系字典（GET /request/hkai/i18n/，與 PHP 共用同一份 JSON）→ get(key) 查字

class _sc_i18n {
    constructor() {
        this.options = {};
        this.options.lang_url = "/request/hkai/i18n/";

        this.lang_data = {};
    }

    // bootstrap 自動呼叫：先讀頁面嵌入嘅字典 island（同步——零請求）；冇才 fetch fallback
    init() {
        const dict_element = document.getElementById("i18n-dict");

        // 判定：有 island → 即場解析（字典即刻可用，唔會有 key 漏出）
        if (dict_element !== null) {
            try {
                const dict_data = JSON.parse(dict_element.textContent);

                // 判定：解析出物件 → 採用，唔再發請求
                if (dict_data !== null && typeof dict_data === "object") {
                    this.lang_data = dict_data;

                    return;
                }
            } catch (parse_error) {
                sc_console("i18n").error("island parse failed:", parse_error.message);
            }
        }

        // island 缺失／壞 → 退回請求
        this.fetch_lang_data();
    }

    async fetch_lang_data() {
        try {
            const response = await fetch(this.options.lang_url);
            const result = await response.json();
            if (!response.ok) {
                sc_console("i18n").error("fetch failed:", result.error);
                return;
            }
            this.lang_data = result.lang_data;
        } catch (fetch_error) {
            // 網路／解析失敗 → console.error（native 錯誤訊息）
            sc_console("i18n").error("fetch failed:", fetch_error.message);
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
