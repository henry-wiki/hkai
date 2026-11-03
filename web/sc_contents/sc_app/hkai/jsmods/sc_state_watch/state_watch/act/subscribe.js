// jsmods/sc_state_watch/state_watch/act/subscribe.js —— 訂閱值變（act——開單叫工作）
// 職責：向 store 登記 handler（值一變通知）
// 上游：任何要監聽狀態看守值嘅模組
// 下游：store
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _subscribe {
    constructor() {}

    // 訂閱（回 unsubscribe）
    async subscribe(target, field, handler) {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const store_instance = await state_watch_domain.store();

        return store_instance.subscribe(target, field, handler);
    }
}

export const subscribe = (function (instance) {
    return function () {

        return instance;
    };
})(new _subscribe());
