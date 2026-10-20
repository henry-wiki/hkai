// jsmods/chat/act/chat_item_fetch.js —— 單項對話內容換入（act 層）
// 職責：chat_item_fetch（單項抓新內容 → 只換 children——節點身份保留、觀察器唔斷；狀態 class 交健康檢查鏈）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_item_fetch {
    constructor() {}

    // 單項對話內容換入（開單叫工作——連線交同域 req；成功＝依 chat_sid 取節點換 children）
    async chat_item_fetch(chat_sid) {
        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_item_fetch_req_instance = await chat_instance.req().chat_item_fetch();

        await chat_item_fetch_req_instance.chat_item_fetch(chat_sid, {
            success: function (response_data) {
                // 目標項（依 chat_sid 取現行節點——節點身份保留）
                const j_chat_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']");

                // 判定：目標項唔喺清單（已被重繪拆走）→ 唔郁
                if (j_chat_item.length < 1) {
                    return;
                }

                // 新項（離線解析——先落容器再搬）
                const j_fresh_item = jQuery(response_data.html.chat_item);

                // 只換 children（節點身份保留——觀察器掛住嗰粒唔會斷）
                j_chat_item.empty();
                j_chat_item.append(j_fresh_item.children());
            },
            error: function (response_data) {
                // 失敗 → 唔郁（維持現狀——等下一輪更新再抓）
            }
        });
    }
}

export const chat_item_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_item_fetch());
