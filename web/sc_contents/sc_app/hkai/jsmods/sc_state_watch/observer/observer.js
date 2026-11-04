// jsmods/sc_state_watch/observer/observer.js —— 監視器容器觀察者（根層）
// 職責：只盯 .sc_state_watch 直接子節點（[data-target]）——target 一增加 → 用對應 observer_<target>.js 開監視；target 移除 → 收掉監視
// 上游：state_watch.init／state_watch/act/write（投影後確保已啟動）
// 下游：observer_<target>.js（逐個 target 一個檔）＋外面登記嘅 handler（用 target 字眼登記，例如 "polling"）
// 界線：只觀察自己容器，唔碰任何外部節點

import { observer_chat }    from "./observer_chat.js";
import { observer_polling } from "./observer_polling.js";
import { observer_prompt }  from "./observer_prompt.js";

const ROOT_SELECTOR = ".sc_state_watch";

class _observer {
    constructor() {
        // 根觀察者實例（未啟動＝null）
        this.observer_instance = null;

        // 每個 target 嘅監視實例（key＝target 節點；value＝observer_<target> 實例）
        this.target_observers = new Map();

        // 外面登記嘅 handler（key＝target 字眼，例如 "polling"）
        this.handlers = new Map();
    }

    // 啟動根觀察者（已有實例＝零動作；回 bool＝有冇新啟動）
    start() {
        // 判定：已啟動 → 唔重複開
        if (this.observer_instance !== null) {
            return false;
        }

        const root_element = document.querySelector(ROOT_SELECTOR);

        // 判定：容器未出現 → 等下一次投影再試
        if (root_element === null) {
            return false;
        }

        this.observer_instance = new MutationObserver(this._handle_root_mutations.bind(this));
        this.observer_instance.observe(root_element, {
            childList: true,
        });

        // 已存在嘅 target 逐一補開監視（啟動前已建立嘅）
        for (let child_index = 0; child_index < root_element.children.length; child_index++) {
            this._bind_target(root_element.children[child_index]);
        }

        return true;
    }

    // 外面登記 handler（target 字眼；回 unsubscribe）
    subscribe(target_name, handler) {
        const target_handlers = this.handlers.get(target_name) || [];

        target_handlers.push(handler);
        this.handlers.set(target_name, target_handlers);

        const thisObject = this;

        return function () {
            const handler_items = thisObject.handlers.get(target_name) || [];
            const handler_index = handler_items.indexOf(handler);

            // 判定：已移除 → 零動作
            if (handler_index < 0) {
                return;
            }

            handler_items.splice(handler_index, 1);
        };
    }

    // 派一筆變動給對應 target 嘅 handler
    dispatch(change_entry) {
        const handler_items = this.handlers.get(change_entry.target) || [];

        sc_console("state_watch", "observer").log("action=" + change_entry.action
            + " modify_type=" + change_entry.modify_type
            + " target=" + change_entry.target
            + " unit=" + change_entry.unit
            + " field=" + change_entry.field
            + " value=" + change_entry.value
            + " handlers=" + handler_items.length);

        for (let handler_index = 0; handler_index < handler_items.length; handler_index++) {
            const handler_item = handler_items[handler_index];

            handler_item(change_entry);
        }
    }

    // 綁一個 target（逐個 target 明寫——四個 observer_*.js 一對一）
    _bind_target(target_element) {
        const target_name = target_element.getAttribute("data-target");

        // 判定：唔係 target 節點 → 跳過
        if (!target_name) {
            return;
        }

        // 判定：已綁過 → 唔重複綁
        if (this.target_observers.has(target_element) === true) {
            return;
        }

        // 判定：polling → 用 polling 觀察器
        if (target_name === "polling") {
            this._attach_target_observer(target_element, observer_polling());

            return;
        }

        // 判定：chat → 用 chat 觀察器
        if (target_name === "chat") {
            this._attach_target_observer(target_element, observer_chat());

            return;
        }

        // 判定：project → 暫時唔監視（未有消費者）
        if (target_name === "project") {
            return;
        }

        // 判定：prompt → 用 prompt 觀察器
        if (target_name === "prompt") {
            this._attach_target_observer(target_element, observer_prompt());

            return;
        }

        // 判定：git → 暫時唔監視（未有消費者）
        if (target_name === "git") {
            return;
        }

        // 判定：conn → 暫時唔監視（未有消費者）
        if (target_name === "conn") {
            return;
        }

        sc_console("state_watch", "observer").warn("未知 target", target_name);
    }

    // 掛上一個 target 觀察器（watch＋記住實例）
    _attach_target_observer(target_element, target_observer) {
        target_observer.watch(target_element, this.dispatch.bind(this));
        this.target_observers.set(target_element, target_observer);
    }

    // 收一個 target（target 已移除——收監視；唔做任何內容改動）
    _unbind_target(target_element) {
        const target_observer = this.target_observers.get(target_element);

        // 判定：冇綁過 → 零動作
        if (target_observer === undefined) {
            return;
        }

        target_observer.unwatch();
        this.target_observers.delete(target_element);
    }

    // 處理根層變動（只理 target 加／減）
    _handle_root_mutations(mutation_records) {
        for (let record_index = 0; record_index < mutation_records.length; record_index++) {
            const mutation_record = mutation_records[record_index];

            // 逐個新增 target
            for (let node_index = 0; node_index < mutation_record.addedNodes.length; node_index++) {
                const added_node = mutation_record.addedNodes[node_index];

                this._bind_target(added_node);
            }

            // 逐個移除 target
            for (let node_index = 0; node_index < mutation_record.removedNodes.length; node_index++) {
                const removed_node = mutation_record.removedNodes[node_index];

                this._unbind_target(removed_node);
            }
        }
    }
}

export const observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _observer());
