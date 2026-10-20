// jsmods/tab_git/req/git_init.js —— 執行 git init（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——git_init（修復動作——端點名＝動作）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _git_init {
    constructor() {}

    // 執行 git init（修復動作——端點名＝動作）
    async git_init(project_slug, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        if (

            callbacks

            &&  callbacks.before_load

        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/git_init/", { project_slug: project_slug || "" });

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

export const git_init = (function (instance) {
    return function () {
        return instance;
    };
})(new _git_init());
