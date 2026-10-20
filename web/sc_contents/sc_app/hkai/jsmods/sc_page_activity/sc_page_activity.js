// jsmods/sc_page_activity/sc_page_activity.js —— 頁面活動讀寫（app 層——跨 plugin 共用）
// 職責：sc_page_activity（set_activity／get_activity——兩端點 POST；fetch 層只回結果、決策交呼叫端）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _sc_page_activity {
    constructor() {}

    // 寫入頁面活動（POST set_activity——scope 帶 project_id／chat_id（可選 default []）；回後端標準結果；網路失敗回 null）
    async set_activity(activity_name, activity_bundle, activity_scope = []) {
        const payload = {};

        payload.activity_name   = activity_name;
        payload.activity_bundle = activity_bundle;
        payload.activity_scope  = activity_scope;

        const result = await registry().shared().sc_request().post_json("/request/hkai/set_activity/", payload);

        return result;
    }

    // 讀取頁面活動（POST get_activity——scope 帶 project_id／chat_id（可選 default []）；成功回 activity_bundle（查無＝null）；網路／伺服器失敗回 null）
    async get_activity(activity_name, activity_scope = []) {
        const payload = {};

        payload.activity_name  = activity_name;
        payload.activity_scope = activity_scope;

        const result = await registry().shared().sc_request().post_json("/request/hkai/get_activity/", payload);

        // 網路／解析層失敗 → null
        if (result === null) {
            return null;
        }

        // 伺服器失敗（ok:false）→ null
        if (!result.ok) {
            return null;
        }

        return result.activity_bundle;
    }
}

export const sc_page_activity = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_page_activity());
