// jsmods/prompt/req/req_draft_save_meta.js —— 草稿資料即時保存（連線側——一 req 一檔）
// 職責：文字／model／reasoning／chat_mode → meta.json（後端補 agent）

class _req_draft_save_meta {
    constructor() {}

    async draft_save_meta(payload, callbacks) {
        const shared = registry().shared();
        const result = await shared.sc_request().post_json("/request/hkai/draft_save_meta/", payload);

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

export const draft_save_meta = (function (instance) {
    return function () {

        return instance;
    };
})(new _req_draft_save_meta());
