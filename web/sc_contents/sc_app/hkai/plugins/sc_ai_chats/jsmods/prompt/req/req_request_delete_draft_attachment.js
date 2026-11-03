// jsmods/prompt/req/req_request_delete_draft_attachment.js —— 刪草稿附件（連線側——一 req 一檔）
// 職責：前端 × 掣 → 後端刪檔＋meta 同步

class _req_request_delete_draft_attachment {
    constructor() {}

    async request_delete_draft_attachment(payload, callbacks) {
        const shared = registry().shared();
        const result = await shared.sc_request().post_json("/request/hkai/request_delete_draft_attachment/", payload);

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

export const request_delete_draft_attachment = (function (instance) {
    return function () {

        return instance;
    };
})(new _req_request_delete_draft_attachment());
