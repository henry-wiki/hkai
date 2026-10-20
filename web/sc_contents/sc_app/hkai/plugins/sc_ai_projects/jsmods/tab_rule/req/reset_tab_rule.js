// jsmods/tab_rule/req/reset_tab_rule.js —— 重設規則（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——reset_tab_rule（清除規則檔——端點名＝動作）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _reset_tab_rule {
    constructor() {}

    // 重設規則（端點名＝動作——project_slug＋opened_update_time）
    async reset_tab_rule(project_slug, opened_update_time, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        if (

            callbacks

            &&  callbacks.before_load

        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/reset_tab_rule/", { project_slug: project_slug || "", opened_update_time: opened_update_time || "" });

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

export const reset_tab_rule = (function (instance) {
    return function () {

        return instance;
    };
})(new _reset_tab_rule());
