// jsmods/modal/req/fetch_project_form.js —— 取 modal 外框內容（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——fetch_project_form（header／tabs／footer；params 帶 project_slug／tab_id）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _fetch_project_form {
    constructor() {}

    // 取 modal 外框內容（header／tabs／footer——params 帶 project_slug／tab_id；冇 params = 新專案）
    async fetch_project_form(params, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        if (

            callbacks

            &&  callbacks.before_load

        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/fetch_project_form/", params || {});

        if (result == null) {
            if (
                callbacks
                &&  callbacks.error
            ) {
                callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });
            }
            return;
        }

        if (result.ok === false) {
            if (
                callbacks
                &&  callbacks.error
            ) {
                callbacks.error(result);
            }
            return;
        }

        if (result.ok === true) {
            if (
                callbacks
                &&  callbacks.success
            ) {
                callbacks.success(result);
            }
            return;
        }
    }
}

export const fetch_project_form = (function (instance) {
    return function () {
        return instance;
    };
})(new _fetch_project_form());
