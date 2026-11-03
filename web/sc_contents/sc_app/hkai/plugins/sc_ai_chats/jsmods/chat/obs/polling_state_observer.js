// jsmods/chat/obs/polling_state_observer.js —— 向 sc_state_watch 登記 polling 通知（接駁）
// 職責：用 "polling" 字眼登記一個 handler；一收到「有變」通知就自行掃描＋做想做嘅事
// 上游：chat 域 bind_events（開域一次）
// 下游：sc_state_watch 域 observer().subscribe()；讀值用 store().snapshot("polling")
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

class _polling_state_observer {
    constructor() {
        // 取消登記函數（未登記＝null）
        this.unsubscribe = null;
    }

    // 登記（重複呼叫只登記一次）
    async init() {
        // 判定：已登記 → 直回
        if (this.unsubscribe !== null) {
            return this;
        }

        const state_watch_module    = registry().sc_state_watch();
        const state_watch_domain    = state_watch_module.state_watch();
        const observer_instance     = await state_watch_domain.observer();

        this.unsubscribe = observer_instance.subscribe("polling", this.refresh_polling_cards_on_store_change.bind(this));

        return this;
    }

    // 收到「有變」通知（唔帶資料——自行掃描）
    async refresh_polling_cards_on_store_change() {
        const state_watch_module    = registry().sc_state_watch();
        const state_watch_domain    = state_watch_module.state_watch();
        const store_instance        = await state_watch_domain.store();
        const polling_snapshot      = store_instance.snapshot("polling");

        sc_console("refresh_polling_cards_from_store").log(polling_snapshot);

        sc_console("polling").log("有變——自行掃描", polling_snapshot);

        this.refresh_project_polling(polling_snapshot.project || {});
        this.refresh_chat_polling(polling_snapshot.chat || {});
    }

    // 用 store 現值重套全部卡（卡片被換／新插入後補回正確狀態）
    async refresh_polling_cards_from_store() {
        const state_watch_module    = registry().sc_state_watch();
        const state_watch_domain    = state_watch_module.state_watch();
        const store_instance        = await state_watch_domain.store();
        const polling_snapshot      = store_instance.snapshot("polling");


        this.refresh_project_polling(polling_snapshot.project || {});
        this.refresh_chat_polling(polling_snapshot.chat || {});
    }


    // 專案卡：{slug: 數}（全量比對——快照冇此專案＝冇數）
    refresh_project_polling(project_counts) {
        const j_project_cards = jQuery(".sc_project_card");

       // console.log("------------------------",project_counts);

        for (let card_index = 0; card_index < j_project_cards.length; card_index++) {
            const j_project_card = jQuery(j_project_cards[card_index]);
            const project_slug = j_project_card.attr("data-project_slug") || "";
            const project_count = project_counts[project_slug] || 0;

            // 判定：有數 → 加標記；否則 → 除標記
            if (project_count > 0) {
                j_project_card.addClass("polling");
            } else {
                j_project_card.removeClass("polling");
            }
        }
    }

    // 對話卡：{chat_sid: 數}（全量比對——快照冇此對話＝冇數）
    refresh_chat_polling(chat_counts) {
        const j_chat_cards = jQuery(".sc_chat_card");

       // console.log("------------------------", chat_counts);

        for (let card_index = 0; card_index < j_chat_cards.length; card_index++) {
            const j_chat_card = jQuery(j_chat_cards[card_index]);
            const chat_sid = j_chat_card.attr("data-chat_sid") || "";
            const chat_count = chat_counts[chat_sid] || 0;

            // 判定：有數 → 加標記；否則 → 除標記
            if (chat_count > 0) {
                j_chat_card.addClass("polling");
            } else {
                j_chat_card.removeClass("polling");
            }
        }
    }
}

export const polling_state_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _polling_state_observer());
