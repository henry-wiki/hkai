// jsmods/message/act/message_list_default.js —— 未有訊息窗狀態（act 層——一檔一事）
// 職責：message_list_default（沒有 message_list 情況——清窗口＋亮常駐空狀態＋落 marker＋清窗口游標＋data-loaded＋派 load_message_list；回傳 true）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_list_default {
    constructor() {}

    // 未有訊息窗（未選對話——純本機呈現；回傳成功旗 true）
    async message_list_default() {
        // 橋接駁（同域——hook 派發用）
        const chats = registry().sc_ai_chats();

        // 窗口容器＋常駐空狀態節點（認 sc_thread__empty_state——後台插入嘅空狀態唔算）
        const j_messages = jQuery(".sc_thread__viewport");
        const j_empty_state = j_messages.children(".sc_thread__empty_state");

        // 清舊訊息（錨點同常駐空狀態節點保留——後台插入嘅空狀態一併清）
        j_messages.children().not("#sc_scroll_anchor").not(".sc_thread__empty_state").remove();

        // 專案判定（SSOT＝projects 域——URL）：有專案＝「請開始你的對話」；未選專案＝「請選擇專案」
        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();

        const j_no_project_state = jQuery(".sc_thread > .sc_empty_state");

        // 判定：有專案 → 顯示常駐「請開始你的對話」；隱藏「請選擇專案」背景（節點長駐——唔移除，返 home 要再用）
        if (project_slug !== "") {
            j_empty_state.prop("hidden", false);
            j_no_project_state.prop("hidden", true);
        // 否則＝未選專案 → 顯示「請選擇專案」；隱藏「請開始你的對話」
        } else {
            j_empty_state.prop("hidden", true);
            j_no_project_state.prop("hidden", false);
        }

        // 落 chat_sid marker＋清窗口游標（未選對話＝未有窗口——向上補載據此唔做）
        j_messages.attr("data-chat_sid", "");
        j_messages.attr("data-message_index", "");
        j_messages.attr("data-message_limit", "");
        j_messages.attr("data-has_more", "0");

        // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
        j_messages.attr("data-loaded", "1");

        // 訊息窗口載入完成 → 派發 load_message_list hook（外部用家各自嘅反應）
        chats.execute_hook_after("load_message_list", []);

        return true;
    }
}

export const message_list_default = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_list_default());
