// jsmods/tab_git/req/git_commit.js —— 執行提交（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——git_commit（提交準備區（git commit）——端點名＝動作）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _git_commit {
    constructor() {}

    // 提交準備區（端點名＝動作——project_slug）
    async git_commit(project_slug, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // before 程序（經 before_load callback——存在先派發）
        if (

            callbacks

            &&  callbacks.before_load

        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/git_commit/", { project_slug: project_slug || "" });

        // 三態派發：null ⇒ E_NETWORK，ok === false ⇒ callbacks.error(result)，ok === true ⇒ callbacks.success(result)
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

export const git_commit = (function (instance) {
    return function () {
        return instance;
    };
})(new _git_commit());
