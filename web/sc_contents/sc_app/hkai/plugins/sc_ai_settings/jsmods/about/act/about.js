// jsmods/about/act/about.js —— about 域動作（act——開單叫工作）
// 職責：fetch_about（POST 取關於 HKAI 分頁內容 HTML；失敗回 ""）
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _about {
    constructor() {}

    // 載入（POST section；回傳 About 分頁內容 HTML——header 注入 modal__header；失敗回 ""）
    async fetch_about() {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_about/", { section: "about" });

        // 網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        return result;
    }
}

export const about = (function (instance) {
    return function () {

        return instance;
    };
})(new _about());
