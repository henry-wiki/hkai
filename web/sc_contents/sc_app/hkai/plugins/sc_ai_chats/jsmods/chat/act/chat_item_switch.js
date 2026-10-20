// jsmods/chat/act/chat_item_switch.js —— 切換對話（act 層——換 URL＋換主；換完由 URL 鏈接手）
// 職責：chat_item_switch（切換對話——換 URL（無痕）＋換主（窗側主鍵對齊＋快照復位＋清單高亮））
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_item_switch {
    constructor() {}

    // 切換對話（點擊／URL 換對話——換 URL（無痕，不重整）＋換主＝窗側主鍵對齊＋清單高亮）
    // chat_sid 空字串 = 未選擇對話（專案根——例：刪掉最後一個對話且同專案已無其他對話）
    async chat_item_switch(chat_sid) {
        const chat_instance = await registry().sc_ai_chats().chat();

        // 窗側主鍵對齊（換主——清單卡片帶更新時間；先換，等載入讀到新主）
        const j_switched_chat_item = jQuery(".sc_chat_list").children(".sc_chat_card[data-chat_sid='" + chat_sid + "']");
        const switched_chat_update_time = j_switched_chat_item.attr("data-update_time") || "";

        jQuery(".sc_thread__viewport").attr("data-chat_sid", chat_sid);
        jQuery(".sc_thread__viewport").attr("data-update_time", switched_chat_update_time);

        // 「係唔係最底」快照復位（換主＝開新窗態——新對話由跟底起步；舊對話嘅瀏覽態唔帶過去）
        jQuery(".sc_thread__viewport").attr("data-is_at_bottom", "1");

        // 清單高亮（清單唔重繪——舊 active 落、新 active 上；未選擇對話＝全落）
        jQuery(".sc_chat_list").children(".sc_chat_card--active").removeClass("sc_chat_card--active");

        j_switched_chat_item.addClass("sc_chat_card--active");
    }
}

export const chat_item_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_item_switch());
