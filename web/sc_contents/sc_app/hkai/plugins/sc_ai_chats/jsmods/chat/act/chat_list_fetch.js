// jsmods/chat/act/chat_list_fetch.js —— 對話清單載入與刷新（act 層——一檔一事）
// 職責：chat_list_fetch（清單載入——hook 連鎖：before 派發 → req → 清單套用 → after 派發）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_list_fetch {
    constructor() {}

    // 清單載入（頁面載入／手動切換——hook 連鎖：before 派發 → req → 清單套用 → after 派發；home（未選專案）照發——後端回預設內容（請選擇專案空狀態））
    async chat_list_fetch() {
        // 派發 load_chat_list hook（before 相位——清單載入前；跨模組用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_before("load_chat_list", []);

        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_list_fetch_instance = await chat_instance.req().chat_list_fetch();

        return await chat_list_fetch_instance.chat_list_fetch({
            success: async function (response_data) {
                // 清單套用（diff——remove／insert／replaceWith；次序交 chat_list_rank_refresh）
                const j_chat_list     = jQuery(".sc_chat_list");
                const j_sidebar_chats = jQuery(".sc_sidebar--chats");
                j_chat_list.empty();
                j_chat_list.append(response_data.html.chat_list);

                // 頭部 kicker 套用（目前專案名——換專案後跟住變；空專案＝空）
                const j_chat_kicker = jQuery(".sc_sidebar--chats .sc_panel_header__kicker");
                j_chat_kicker.html(response_data.html.chat_header_kicker);

                // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
                j_chat_list.attr("data-loaded", "1");

                // 判定：歸檔語境（旗標由後台出）→ 上 .sc_chat_list.archive（卡片區）＋ .sc_sidebar--chats.archive（header 區）
                if (response_data.is_archive === "1") {
                    j_chat_list.addClass("archive");
                    j_sidebar_chats.addClass("archive");
                } else {
                    j_chat_list.removeClass("archive");
                    j_sidebar_chats.removeClass("archive");
                }

                // 派發 load_chat_list hook（外部用家各自嘅反應——連載訊息／prompt）
                registry().sc_ai_chats().execute_hook_after("load_chat_list", response_data);
            },
            error: async function (response_data) {
                // 失敗（例：URL 專案唔存在）→ 派 failed 相位（清單維持現狀——等下次切換再抓）
                registry().sc_ai_chats().execute_hook_failed("load_chat_list", response_data);
            }
        });
    }

    // 對話清單重載（重抓並重繪；成功後由 load_chat_list hook 連載訊息窗口）
    async refresh_chat_list() {
        return this.chat_list_fetch();
    }
}

export const chat_list_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_list_fetch());
