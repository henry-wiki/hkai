// jsmods/sc_state_watch/observer/observer_prompt.js —— prompt target 觀察器（骨架——未實作）
// 職責：盯住 prompt target 底下內容 → 交 root observer 派給用 "prompt" 登記嘅 handler
// 狀態：只建骨架（watch 暫時唔開監視）；實作時照 observer_polling.js 形
// 結構：export const + eager 單例 closure factory（靜態 import 用——零 .default）

class _observer_prompt {
    constructor() {
        // 內容觀察器（未開＝null）
        this.mutation_observer = null;
    }

    // 開始監視（未實作——回 false）
    watch(target_element, dispatch) {
        console.warn("sc_state_watch observer: observer_prompt 未實作");

        return false;
    }

    // 收掉監視（未實作——零動作）
    unwatch() {
    }
}

export const observer_prompt = (function (instance) {
    return function () {

        return instance;
    };
})(new _observer_prompt());
