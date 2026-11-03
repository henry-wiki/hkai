// jsmods/chat/act/chat_list_fetch.js —— 對話清單載入與刷新（act 層——一檔一事）
// 職責：chat_list_fetch（清單載入——hook 連鎖：before 派發 → req → 清單套用 → after 派發）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_list_fetch {
    constructor() {}

    // 選中守衛：頁面任何節點變動（清單重繪／卡片補插）→ 主鍵對話保持 active
    _ensure_active_guard() {
        const thisObject = this;

        // 判定：已裝 → 唔重複（觀察 document.body——sidebar 換節點都唔會失效）
        if (this.active_guard_observer) {
            return;
        }

        const observer = new MutationObserver(function () {
            const current_chat_sid = String(jQuery(".sc_thread__viewport").attr("data-chat_sid") || "");

            // 判定：未有主鍵 → 唔動
            if (current_chat_sid === "") {
                return;
            }

            const j_active_card = jQuery(".sc_chat_list .sc_chat_card--active[data-chat_sid='" + current_chat_sid + "']");

            // 判定：已正確選中 → 唔動
            if (j_active_card.length === 1) {
                return;
            }

            thisObject._apply_active(current_chat_sid);
        });

        observer.observe(document.body, {childList: true, subtree: true});

        this.active_guard_observer = observer;
    }

    // 套用選中（單一入口——移除其他、標記目標）
    _apply_active(chat_sid) {
        jQuery(".sc_chat_list").children(".sc_chat_card--active").removeClass("sc_chat_card--active");

        jQuery(".sc_chat_list").children(".sc_chat_card[data-chat_sid='" + chat_sid + "']").addClass("sc_chat_card--active");
    }

    // 清單載入（頁面載入／手動切換——hook 連鎖：before 派發 → req → 清單套用 → after 派發；home（未選專案）照發——後端回預設內容（請選擇專案空狀態））
    async chat_list_fetch() {
        // 派發 load_chat_list hook（before 相位——清單載入前；跨模組用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_before("load_chat_list", []);

        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_list_fetch_instance = await chat_instance.req().chat_list_fetch();

        return await chat_list_fetch_instance.chat_list_fetch({
            success: async (response_data) => {
                // 清單套用（diff——remove／insert／replaceWith；次序由 chat_state_observer 重排）
                const j_chat_list     = jQuery(".sc_chat_list");
                const j_sidebar_chats = jQuery(".sc_sidebar--chats");
                j_chat_list.empty();
                j_chat_list.append(response_data.html.chat_list);

                // 頭部 kicker 套用（目前專案名——換專案後跟住變；空專案＝空）
                const j_chat_kicker = jQuery(".sc_sidebar--chats .sc_panel_header__kicker");
                j_chat_kicker.html(response_data.html.chat_header_kicker);

                // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
                j_chat_list.attr("data-loaded", "1");

                // 清單重繪會抹走 polling 標記 → 用 store 现值重套
                const polling_state_observer_instance = await chat_instance.obs().polling_state_observer();

                await polling_state_observer_instance.refresh_polling_cards_from_store();

                // 清單重繪亦會令卡面停留喺後端 HTML 舊值 → 用 store 現值重套（msg_count／lastupdate／status）
                const chat_state_observer_instance = await chat_instance.obs().chat_state_observer();

                await chat_state_observer_instance.apply_current();

                // 判定：歸檔語境（旗標由後台出）→ 上 .sc_chat_list.archived（卡片區）＋ .sc_sidebar--chats.archived（header 區）
                if (response_data.is_archive === "1") {
                    j_chat_list.addClass("archived");
                    j_sidebar_chats.addClass("archived");
                } else {
                    j_chat_list.removeClass("archived");
                    j_sidebar_chats.removeClass("archived");
                }

                // 派發 load_chat_list hook（外部用家各自嘅反應——連載訊息／prompt）
                registry().sc_ai_chats().execute_hook_after("load_chat_list", response_data);

                // 選中守衛（清單日後任何變動都由觀察器補回）
                this._ensure_active_guard();

                // 清單重繪後補回選中（目前對話——view 側主鍵；SSOT＝窗側 data-chat_sid）
                const current_chat_sid = String(jQuery(".sc_thread__viewport").attr("data-chat_sid") || "");

                // 判定：未有主鍵（頁面初次載入）→ 保留後端已出嘅 active，唔覆蓋
                if (current_chat_sid !== "") {
                    const chat_item_switch_instance = await chat_instance.act().chat_item_switch();

                    await chat_item_switch_instance.chat_item_switch(current_chat_sid);
                }
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
