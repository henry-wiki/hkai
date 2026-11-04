// jsmods/chat/obs/chat_card_observer.js —— 對話卡增減觀察（只捕捉「冇→有」同「有→冇」）
// 職責：盯 .sc_chat_list 直接子節點（childList、唔落 subtree）——卡片出現／消失各報一次；卡片內部值變一概唔報
// 上游：chat 域 bind_events（開域一次）
// 下游：hook obs:chat_card_changed（action＋chat_sid）；本檔唔判斷做咩
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

class _chat_card_observer {
    constructor() {
        // 觀察器（重掛先 disconnect）
        this.mutation_observer = null;
    }

    // 建立／重掛觀察器（可重複呼叫——重掛先解除）
    init() {
        // 判定：舊觀察器存在 → 先解除（重掛唔殘留）
        if (this.mutation_observer !== null) {
            this.mutation_observer.disconnect();
            this.mutation_observer = null;
        }

        const chat_list_element = document.querySelector(".sc_chat_list");

        // 判定：清單未出現 → 唔掛（等下次）
        if (chat_list_element === null) {
            return;
        }

        this.mutation_observer = new MutationObserver(this._handle_mutations.bind(this));
        this.mutation_observer.observe(chat_list_element, {
            childList: true,
        });
    }

    // 處理清單直接子節點增減（只認 .sc_chat_card）
    _handle_mutations(mutation_records) {
        for (let record_index = 0; record_index < mutation_records.length; record_index++) {
            const mutation_record = mutation_records[record_index];

            // 逐個新增卡
            for (let node_index = 0; node_index < mutation_record.addedNodes.length; node_index++) {
                const added_node = mutation_record.addedNodes[node_index];

                this._report("added", added_node);
            }

            // 逐個移除卡
            for (let node_index = 0; node_index < mutation_record.removedNodes.length; node_index++) {
                const removed_node = mutation_record.removedNodes[node_index];

                this._report("deleted", removed_node);
            }
        }
    }

    // 報一筆增減（唔係對話卡＝跳過）
    _report(action, node) {
        // 判定：唔係元素節點 → 跳過
        if (!node || node.nodeType !== 1) {
            return;
        }

        // 判定：唔係對話卡 → 跳過（例如空清單佔位）
        if (node.classList.contains("sc_chat_card") === false) {
            return;
        }

        const chat_sid = node.getAttribute("data-chat_sid");

        sc_console("chat_card").log(action + " " + chat_sid);

        registry().sc_ai_chats().execute_hook_after("obs:chat_card_changed", [action, chat_sid]);
    }
}

export const chat_card_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_card_observer());
