// jsmods/chat/act/chat_item_fetch.js —— 單張對話卡取回（act 層——一檔一事；主動作＝檔名）
// 職責：chat_item_fetch（收斂迴圈「增加」變更用——取單張卡 HTML 交呼叫端插入；插入位置交 rank 升位）
// 上游：chat obs chat_snapshot_observer（快照有、畫面冇）
// 下游：chat req chat_item_fetch

class _chat_item_fetch {
    constructor() {}

    // 取單張卡 HTML（回 html.chat_item 字串；成敗交 callbacks）
    async chat_item_fetch(chat_sid, callbacks) {
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_item_fetch_req_instance = await chat_instance.req().chat_item_fetch();

        await chat_item_fetch_req_instance.chat_item_fetch(chat_sid, {
            success: async function (response_data) {
                await callbacks.success(response_data);
            },
            error: async function (response_data) {
                await callbacks.error(response_data);
            }
        });
    }
}

export const chat_item_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_item_fetch());
