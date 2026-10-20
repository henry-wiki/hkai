// jsmods/bind/changed_message_state.js —— .sc_thread__viewport 狀態變動觀察（bind 層——一檔一事）
// 職責：changed_message_state（MutationObserver .sc_thread__viewport → 同步 sb_state）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _changed_message_state {
    constructor() {
        // .sc_thread__viewport 嘅 MutationObserver 實例（init 註冊）
        this.state_observer = null;
    }

    // 觀察處理狀態（.sc_thread__viewport 內 class 變動——消息區由既有 chats 模組渲染）
    init() {
        const thisObject = this;

        const j_messages = document.querySelector(".sc_thread__viewport");
        if (!j_messages) {
            return;
        }

        thisObject.state_observer = new MutationObserver(function () {
            thisObject.changed_message_state();
        });

        const observer_options = {};
        observer_options.attributes = true;
        observer_options.attributeFilter = ["class"];
        observer_options.childList = true;
        observer_options.subtree = true;
        thisObject.state_observer.observe(j_messages, observer_options);
    }

    // 發現變動 → 同步 sb_state（原 observe_state 內匿名回呼——交 callback/sync_status_items）
    async changed_message_state() {
        // 動態載入（callback——sync status items）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const sync_status_items_instance = await status_bar_domain.act().sync_status_items();

        sync_status_items_instance.sync_state();
    }
}

export const changed_message_state = (function (instance) {
    return function () {

        return instance;
    };
})(new _changed_message_state());
