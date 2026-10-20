// jsmods/bind/changed_chat_options.js —— .sc_composer 變動觀察（bind 層——一檔一事）
// 職責：changed_chat_options（MutationObserver .sc_composer → 同步 sb_model）
// 備註：盯穩定外框——.__options 子節點每次切對話被換走，盯佢會斷
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _changed_chat_options {
    constructor() {
        // .sc_composer 嘅 MutationObserver 實例（init 註冊）
        this.model_observer = null;
    }

    // 觀察模型／模式變動（chat options 區塊——既有模組切換時同步）
    init() {
        const thisObject = this;

        const j_chat_options = document.querySelector(".sc_composer");
        if (!j_chat_options) {
            return;
        }

        thisObject.model_observer = new MutationObserver(function () {
            thisObject.changed_chat_options();
        });

        const observer_options = {};
        observer_options.attributes = true;
        observer_options.attributeFilter = ["class"];
        observer_options.childList = true;
        observer_options.characterData = true;
        observer_options.subtree = true;
        thisObject.model_observer.observe(j_chat_options, observer_options);
    }

    // 發現變動 → 同步 sb_model（原 observe_model 內匿名回呼——交 callback/sync_status_items）
    async changed_chat_options() {
        // 動態載入（callback——sync status items）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const sync_status_items_instance = await status_bar_domain.act().sync_status_items();

        sync_status_items_instance.sync_model();
    }
}

export const changed_chat_options = (function (instance) {
    return function () {

        return instance;
    };
})(new _changed_chat_options());
