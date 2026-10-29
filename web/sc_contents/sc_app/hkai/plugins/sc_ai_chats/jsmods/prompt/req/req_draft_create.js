// jsmods/prompt/req/req_draft_create.js —— 建立草稿（連線側——一 req 一檔）
// 職責：頁面冇草稿時立即建立（後端分配新 prompt_id UUIDv7）

class _req_draft_create {
    constructor() {}

    async draft_create(payload, callbacks) {
        const shared = registry().shared();
        const result = await shared.sc_request().post_json("/request/hkai/draft_create/", payload);

        // 判定：網路失敗 → E_NETWORK
        if (result == null) {
            callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        // 判定：後端拒絕 → error
        if (result.ok === false) {
            callbacks.error(result);

            return;
        }

        callbacks.success(result);
    }
}

export const draft_create = (function (instance) {
    return function () {

        return instance;
    };
})(new _req_draft_create());
