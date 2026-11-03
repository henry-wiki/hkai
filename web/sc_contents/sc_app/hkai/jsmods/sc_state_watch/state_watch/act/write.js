// jsmods/sc_state_watch/state_watch/act/write.js —— 寫入一項登記（act——開單叫工作）
// 職責：store 寫值 → 有變才投影去自有 DOM 容器＋確保觀察者已啟動
// 上游：health stream updater／任何要向狀態看守報數嘅模組
// 下游：store（值）／dom（容器）／observer（觀察者）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _write {
    constructor() {}

    // 寫入一項（entry＝{target,unit,field,value}；回 bool＝有冇改變）
    async write(entry) {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const store_instance = await state_watch_domain.store();
        const has_changed = store_instance.write(entry);

        // 判定：值冇變 → 唔投影、唔通知
        if (has_changed === false) {
            return false;
        }

        // 投影去監視器自己嘅資料容器（三層 DOM——對外可讀）
        const dom_instance = await state_watch_domain.dom();
        dom_instance.project(entry);

        // 投影後確保觀察者已啟動（容器第一次出現時才開得成）
        const observer_instance = await state_watch_domain.observer();
        observer_instance.start();

        return true;
    }
}

export const write = (function (instance) {
    return function () {

        return instance;
    };
})(new _write());
