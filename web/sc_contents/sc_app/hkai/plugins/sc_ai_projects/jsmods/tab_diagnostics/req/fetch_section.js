// jsmods/tab_diagnostics/req/fetch_section.js —— 診斷 section 載入（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——fetch_section（單一 section 掃描結果 HTML——端點名＝動作）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _fetch_section {
    constructor() {}

    // 取診斷 section（端點名＝動作——project_slug＋section_id）
    async fetch_section(project_slug, section_id, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        if (

            callbacks

            &&  callbacks.before_load

        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/fetch_section/", { project_slug: project_slug || "", section_id: section_id || "" });

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

export const fetch_section = (function (instance) {
    return function () {
        return instance;
    };
})(new _fetch_section());
