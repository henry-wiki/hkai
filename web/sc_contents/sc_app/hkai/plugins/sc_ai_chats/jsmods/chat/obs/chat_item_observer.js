// jsmods/chat/obs/chat_item_observer.js —— 對話單項變動觀察綁定（factory 單例——MutationObserver 逐項盯 .sc_chat_card；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——逐個 .sc_chat_card 掛觀察（attributes＋attributeFilter data-update_time）；重掛先 disconnect）② 變化 → 派 hook 信號（obs:chat_item_updated——做咩由 root 側 register_hook 決定；本檔唔判斷）
// 只睇對話項（.sc_chat_card DOM——零後端）；只認 data-update_time（唯一觀察目標——其他屬性唔報）
// 備註：項目集合變動（新項加入／清單重繪）後要再 init 一次——先覆蓋新項（init 可重複呼叫，內置 disconnect 防重）
// 結構：eager 單例 closure factory（具名 export——域接駁 chat.js init 動態 import 解構後呼叫 init()）

class _chat_item_observer {
    constructor() {
        // 對話項觀察器（重掛時先 disconnect）
        this.mutation_observer = null;
    }

    // 建立＋逐項觀察（可重複呼叫——重掛先解除；只認 data-update_time）
    init() {
        const thisObject = this;

        // 判定：舊觀察器存在 → 先解除（重掛唔殘留）
        if (thisObject.mutation_observer) {
            thisObject.mutation_observer.disconnect();
            thisObject.mutation_observer = null;
        }

        // 判定：瀏覽器唔支援 MutationObserver → 唔掛（維持基本行為）
        if (typeof MutationObserver !== "function") {
            return;
        }

        // 現有對話項（逐項掛——集合變動後再 init 覆蓋新項）
        const j_chat_items = jQuery(".sc_chat_list .sc_chat_card");

        // 判定：冇對話項 → 唔掛觀察器
        if (j_chat_items.length < 1) {
            return;
        }

        // 逐項觀察（單一觀察器、多目標 observe；只認 data-update_time）
        thisObject.mutation_observer = new MutationObserver(function (mutation_records) {
            // 受變更嘅對話項（逐筆記錄抽 target——同一項多筆照過；做咩交 downstream）
            const affected_chat_items = [];
            for (let record_index = 0; record_index < mutation_records.length; record_index++) {
                affected_chat_items.push(mutation_records[record_index].target);
            }

            // 派 obs:chat_item_updated 信號（做咩＝root 側 register_hook 嘅事；本檔唔判斷）
            registry().sc_ai_chats().execute_hook_after("obs:chat_item_updated", [affected_chat_items]);
        });

        // 逐項掛（同一觀察器 observe 多個目標——只認 data-update_time）
        for (let item_index = 0; item_index < j_chat_items.length; item_index++) {
            thisObject.mutation_observer.observe(j_chat_items[item_index], {
                attributes: true,
                attributeFilter: ["data-update_time"],
            });
        }
    }

}

export const chat_item_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_item_observer());
