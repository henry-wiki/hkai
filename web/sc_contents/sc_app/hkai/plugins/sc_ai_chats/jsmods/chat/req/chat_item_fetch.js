// jsmods/chat/req/chat_item_fetch.js —— 單張對話卡 HTML 載入（連線側——一 req 一檔；檔名／函數名＝endpoint 名）
// 職責：與伺服器連接部分——chat_item_fetch（組 payload（project_slug＋chat_sid＋selected_chat_sid）→ 後端 chat_item_fetch → 派發）
// 上游：chat act chat_item_fetch（收斂迴圈補卡）
// 下游：sc_request post_json

class _chat_item_fetch {
    constructor() {}

    // 取單張卡 HTML（只做連線：組 payload → 後端 → 派去 callbacks）
    async chat_item_fetch(chat_sid, callbacks) {
        const shared = registry().shared();

        const project_instance = await registry().sc_ai_projects().project();
        const chat_instance    = await registry().sc_ai_chats().chat();

        const payload = {};
        payload.project_slug      = project_instance.get_project_slug();
        payload.chat_sid          = chat_sid;
        payload.selected_chat_sid = chat_instance.get_url_chat_sid();

        const result = await shared.sc_request().post_json("/request/hkai/chat_item_fetch/", payload);

        // 判定：冇回應（網絡失敗）→ 交 error
        if (result == null) {
            await callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        // 判定：後端話失敗 → 交 error
        if (result.ok === false) {
            await callbacks.error(result);

            return;
        }

        await callbacks.success(result);
    }
}

export const chat_item_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_item_fetch());
