// jsmods/handler/settings_fetch.js —— settings 連線側（handler 層——一檔一事）
// 職責：settings_fetch（fetch_settings GET——settings side menu 資料）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _settings_fetch {
    constructor() {}

    // 載入 settings initial data（side menu——setting_sections；失敗回 null）
    async fetch_settings() {
        try {
            const response = await fetch("/request/hkai/fetch_settings/", {
                method: "GET",
            });
            if (!response.ok) {
                return null;
            }
            return await response.json();
        } catch (fetch_error) {
            return null;
        }
    }

}

export const settings_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _settings_fetch());
