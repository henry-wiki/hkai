// jsmods/tab_environment/req/update_tab_environment.js —— 更新環境（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——update_tab_environment（環境重新偵測——端點名＝動作）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _update_tab_environment {
    constructor() {}

    // 更新環境（端點名＝動作——project_slug）
    async update_tab_environment(project_slug, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        if (

            callbacks

            &&  callbacks.before_load

        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/update_tab_environment/", { project_slug: project_slug || "" });

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

export const update_tab_environment = (function (instance) {
    return function () {
        return instance;
    };
})(new _update_tab_environment());
