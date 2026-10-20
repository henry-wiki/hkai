// jsmods/chat/act/chat_item_insert.js —— 新對話項插入（act 層——一檔一事；主動作＝檔名）
// 職責：chat_item_insert（按 chat_sid 後端載入該對話項 HTML → append 入 .sc_chat_list 尾——由 chat_list_refresh 迴圈逐個呼叫）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_item_insert {
    constructor() {}

    // 按 chat_sid 載入並插入單一對話項
    async chat_item_insert(chat_sid) {
        // 判定：已存在 → 唔重複補
        if (jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']").length > 0) {
            return;
        }

        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_item_fetch_req_instance = await chat_instance.req().chat_item_fetch();

        // 橋接駁（同域 obs 橋——成功回呼要同步用佢、預先攞定；新項唔掛觀察器＝時間／count 永遠唔更新）
        const chat_item_observer_instance = await chat_instance.obs().chat_item_observer();

        await chat_item_fetch_req_instance.chat_item_fetch(chat_sid, {
            success: function (response_data) {
                // 新項（離線解析——先落容器再搬）
                const j_new_item = jQuery(response_data.html.chat_item);

                // 判定：已存在（等待期間已由別路補上）→ 唔再加入
                if (jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']").length > 0) {
                    return;
                }

                // 落清單尾（次序交排位者——同一拍 status_refresh 尾會重排）
                jQuery(".sc_chat_list").append(j_new_item);

                // 重掛觀察器（init 全量覆蓋——新項即納入觀察；data-update_time 一變 → 派 obs:chat_item_updated → 抓新內容換入）
                chat_item_observer_instance.init();
            },
            error: function (response_data) {
                // 失敗 → 唔補（維持現狀——等下一輪再試）
            }
        });
    }
}

export const chat_item_insert = (function (instance) {
    return function () {
        return instance;
    };
})(new _chat_item_insert());
