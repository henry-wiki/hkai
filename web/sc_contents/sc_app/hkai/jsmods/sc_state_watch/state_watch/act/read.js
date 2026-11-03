// jsmods/sc_state_watch/state_watch/act/read.js —— 讀取一項登記（act——開單叫工作）
// 職責：由 store 讀值（冇登記＝null）
// 上游：任何要讀狀態看守值嘅模組
// 下游：store
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _read {
    constructor() {}

    // 讀取一項（冇登記＝null——唔做兜底猜測）
    async read(target, unit, field) {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const store_instance = await state_watch_domain.store();

        return store_instance.read(target, unit, field);
    }
}

export const read = (function (instance) {
    return function () {

        return instance;
    };
})(new _read());
