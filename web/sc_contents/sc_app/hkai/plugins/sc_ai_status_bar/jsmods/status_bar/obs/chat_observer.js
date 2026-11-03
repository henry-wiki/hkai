// jsmods/status_bar/obs/chat_observer.js —— 對話清單監視（factory 單例——MutationObserver 盯 .sc_chat_list）
// 職責：清單有變（卡片增減／active 上落）→ 派 hook 信號（obs:chat_active——做咩由 root 側 register_hook 決定）
// 上游：sc_ai_status_bar obs_hook_register；下游：status_bar root hook（重取 chat 區）
// 只睇 DOM（零後端）：唔靠 URL 變化——URL 用 shallow 換時觀察器收唔到

class _chat_observer {
    constructor() {
        // 觀察器（.sc_chat_list 子樹 class／子節點變化）
        this.mutation_observer = null;

        // 合併計時器（同一次重繪多個 mutation → 只派一次信號）
        this.dispatch_timer_id = 0;

        // 上次已通知嘅 active 卡（同一個 active 重繪 → 唔再派）
        this.last_active_chat_sid = null;
    }

    // 建立＋觀察（可重複呼叫——重掛先解除）
    init() {
        const thisObject = this;

        // 判定：已掛 → 先解除（重掛唔殘留）
        if (thisObject.mutation_observer) {
            thisObject.mutation_observer.disconnect();
            thisObject.mutation_observer = null;
        }

        const j_chat_list = jQuery(".sc_chat_list");

        // 判定：清單容器未存在 → 唔掛（等下次頁面重建再 init）
        if (j_chat_list.length < 1) {
            return;
        }

        // 判定：瀏覽器唔支援 → 唔掛
        if (typeof MutationObserver !== "function") {
            return;
        }

        thisObject.mutation_observer = new MutationObserver(function (mutation_records) {
            const has_list_change = thisObject.is_list_change(mutation_records);

            // 判定：唔係清單相關變化 → 唔派
            if (has_list_change === false) {
                return;
            }

            thisObject.schedule_dispatch();
        });

        thisObject.mutation_observer.observe(j_chat_list[0], {
            attributes: true,
            attributeFilter: ["class"],
            childList: true,
            subtree: true,
        });
    }

    // 現時 active 卡嘅 data-chat_sid（冇＝空字串）
    get_active_chat_sid() {
        const j_active_chat = jQuery(".sc_chat_list .sc_chat_card--active");

        // 判定：冇 active 卡 → 空
        if (j_active_chat.length < 1) {
            return "";
        }

        return String(j_active_chat.first().attr("data-chat_sid") || "");
    }

    // 排一次派發（同一輪多個 mutation 合併——避免重複請求）
    schedule_dispatch() {
        const thisObject = this;

        // 判定：已有排程 → 唔重複排（等現有嗰個跑）
        if (thisObject.dispatch_timer_id !== 0) {
            return;
        }

        thisObject.dispatch_timer_id = window.setTimeout(function () {
            thisObject.dispatch_timer_id = 0;

            const current_active_chat_sid = thisObject.get_active_chat_sid();

            // 判定：active 卡冇變 → 唔派（清單重繪／換卡內容唔算切換）
            if (current_active_chat_sid === thisObject.last_active_chat_sid) {
                return;
            }

            thisObject.last_active_chat_sid = current_active_chat_sid;

            registry().sc_ai_status_bar().execute_hook_after("obs:chat_active", []);
        }, 120);
    }

    // 判定：今次變動係唔係「清單有變」（卡片 class 上落／卡片新增移除）
    is_list_change(mutation_records) {
        for (let record_index = 0; record_index < mutation_records.length; record_index++) {
            const mutation_record = mutation_records[record_index];

            // 判定：有節點增減 → 係（歸檔／新增卡）
            if (mutation_record.type === "childList") {
                return true;
            }

            const mutation_target = mutation_record.target;

            // 判定：目標係對話卡 → 係（active 上／落）
            if (jQuery(mutation_target).hasClass("sc_chat_card") === true) {
                return true;
            }
        }

        return false;
    }
}

export const chat_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_observer());
