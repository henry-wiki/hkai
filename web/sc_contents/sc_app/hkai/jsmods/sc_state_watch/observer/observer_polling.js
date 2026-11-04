// jsmods/sc_state_watch/observer/observer_polling.js —— polling target 觀察器（建立監視＋回報變動）
// 職責：盯住 polling target 底下全部內容（entry 加／減／值變）→ 交 root observer 派給已登記 handler
// 上游：observer.js（target 一出現就動態載入本檔並叫 watch）
// 下游：root observer 嘅 dispatch（handler 由外面用 "polling" 字眼登記）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _observer_polling {
    constructor() {
        // 目標節點（target 被移除時用嚟認返）
        this.target_element = null;

        // 內容觀察器
        this.mutation_observer = null;

        // 變動派發（root observer 提供）
        this.dispatch = null;
    }

    // 開始監視一個 polling target（回 bool＝有冇開）
    watch(target_element, dispatch) {
        // 判定：已有監視 → 先收掉（唔殘留）
        if (this.mutation_observer !== null) {
            this.unwatch();
        }

        this.target_element = target_element;
        this.dispatch = dispatch;
        this.mutation_observer = new MutationObserver(this._handle_mutations.bind(this));
        this.mutation_observer.observe(target_element, {
            childList: true,
            subtree: true,
            characterData: true,
        });

        sc_console("state_watch", "observer_polling").log("watch_target polling");

        return true;
    }

    // 收掉監視（冇開＝零動作）
    unwatch() {
        // 判定：冇觀察器 → 零動作
        if (this.mutation_observer === null) {
            return;
        }

        this.mutation_observer.disconnect();
        this.mutation_observer = null;
        this.target_element = null;
        this.dispatch = null;

        sc_console("state_watch", "observer_polling").log("unwatch_target polling");
    }

    // 由節點讀出 entry 身份（unit／field；唔屬 entry＝null）
    _read_entry_identity(node) {
        const unit_name = node.getAttribute("data-unit");
        const field_name = node.getAttribute("data-field");

        // 判定：缺 unit 或 field → 唔屬 entry
        if (!unit_name || !field_name) {
            return null;
        }

        return { unit: unit_name, field: field_name };
    }

    // 處理 mutation records（逐筆轉成 polling 變動）
    _handle_mutations(mutation_records) {
        for (let record_index = 0; record_index < mutation_records.length; record_index++) {
            const mutation_record = mutation_records[record_index];

            // 逐個新增節點
            for (let node_index = 0; node_index < mutation_record.addedNodes.length; node_index++) {
                const added_node = mutation_record.addedNodes[node_index];

                // 判定：文字節點 → 當父 entry 值變
                if (added_node.nodeType === 3) {
                    this._report("modified", "update", added_node.parentElement, added_node.textContent);

                    continue;
                }

                this._report("modified", "add", added_node, added_node.textContent);
            }

            // 逐個移除節點
            for (let node_index = 0; node_index < mutation_record.removedNodes.length; node_index++) {
                const removed_node = mutation_record.removedNodes[node_index];

                // 判定：文字節點 → 當父 entry 值流失（改為空字串）
                if (removed_node.nodeType === 3) {
                    this._report("modified", "update", removed_node.parentElement, "");

                    continue;
                }

                this._report("deleted", "", removed_node, removed_node.textContent);
            }

            // 值變（characterData——Text 節點直接改）
            if (mutation_record.type === "characterData") {
                this._report("modified", "update", mutation_record.target.parentElement, mutation_record.target.textContent);
            }
        }
    }

    // 回報一筆變動（身份唔齊＝跳過；modify_type＝add／update——暫時未用，預留將來細分）
    _report(action, modify_type, entry_element, value) {
        // 判定：冇元素 → 跳過
        if (!entry_element) {
            return;
        }

        const entry_identity = this._read_entry_identity(entry_element);

        // 判定：唔屬 entry → 跳過
        if (entry_identity === null) {
            return;
        }

        this.dispatch({
            target: "polling",
            action: action,
            modify_type: modify_type,
            unit: entry_identity.unit,
            field: entry_identity.field,
            value: value,
        });
    }
}

export const observer_polling = (function (instance) {
    return function () {

        return instance;
    };
})(new _observer_polling());
