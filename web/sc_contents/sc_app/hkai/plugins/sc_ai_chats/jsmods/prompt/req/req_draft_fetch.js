// jsmods/prompt/req/req_draft_fetch.js —— 讀取草稿（連線側——一 req 一檔）
// 職責：重載／還原 → 取 meta＋附件清單（縮圖 data URL）

class _req_draft_fetch {
    constructor() {}

    async draft_fetch(payload, callbacks) {
        const shared = registry().shared();
        const result = await shared.sc_request().post_json("/request/hkai/draft_fetch/", payload);

        // 判定：網路失敗 → E_NETWORK
        if (result == null) {
            callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        // 判定：後端拒絕（404＝冇草稿）→ error
        if (result.ok === false) {
            callbacks.error(result);

            return;
        }

        callbacks.success(result);
    }
}

export const draft_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _req_draft_fetch());
