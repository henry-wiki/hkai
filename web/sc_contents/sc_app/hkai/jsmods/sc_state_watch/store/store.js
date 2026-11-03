// jsmods/sc_state_watch/store/store.js —— 狀態看守值（唯一真相：target:unit:field → value）
// 職責：write（值不變即返）／read／entries_for／subscribe（值一變通知訂閱者）
// 上游：state_watch/act/*（write／read／subscribe）
// 下游：無（純資料，唔掂 DOM）

import { is_known_entry, is_known_unit } from "../contract/contract.js";

class _store {
    constructor() {
        // 登記值（key＝target:unit:field）
        this.values = new Map();

        // 訂閱者（{target, field, handler}）
        this.subscribers = [];
    }

    // 組 key（唯一身份）
    key(target, unit, field) {
        return target + ":" + unit + ":" + field;
    }

    // 寫入（回 bool＝有冇改變；值相同＝零動作）
    write(entry) {
        // 判定：契約唔認得此 target／field → 拒寫（連接方違規——穩定器唔遷就）
        if (is_known_entry(entry.target, entry.field) === false) {
            console.warn("sc_state_watch: 契約外寫入（target／field）", entry);

            return false;
        }

        // 判定：單位空白 → 拒寫（冇身份，無從投影）
        if (typeof entry.unit !== "string" || entry.unit === "") {
            console.warn("sc_state_watch: 契約外寫入（unit）", entry);

            return false;
        }

        // 判定：單位唔在契約清單內 → 拒寫（例如 polling 只准 project／chat）
        if (is_known_unit(entry.target, entry.unit) === false) {
            console.warn("sc_state_watch: 契約外寫入（unit 唔准）", entry);

            return false;
        }

        const store_key         = this.key(entry.target, entry.unit, entry.field);
        const next_value        = this.normalize_value(entry.value);
        const has_key           = this.values.has(store_key);
        const current_value     = has_key ? this.values.get(store_key) : null;

        // 判定：值冇變 → 唔寫、唔通知
        if (has_key === true && current_value === next_value) {
            return false;
        }

        this.values.set(store_key, next_value);
        this.notify(entry.target, entry.unit, entry.field, next_value);

        return true;
    }

    // 由頁面 render 嘅初始值載入（頁面唯一一次——之後只由 SSE 變化更新）
    hydrate_from_dom() {
        const root_element = document.querySelector(".sc_state_watch");

        // 判定：頁面冇容器 → 唔載
        if (root_element === null) {
            return;
        }

        const target_elements = root_element.querySelectorAll("[data-target]");

        for (let target_index = 0; target_index < target_elements.length; target_index++) {
            const target_element = target_elements[target_index];
            const target_name = target_element.getAttribute("data-target");
            const entry_elements = target_element.querySelectorAll("[data-unit][data-field]");

            for (let entry_index = 0; entry_index < entry_elements.length; entry_index++) {
                const entry_element = entry_elements[entry_index];
                const unit_name = entry_element.getAttribute("data-unit");
                const field_name = entry_element.getAttribute("data-field");

                this.write({ target: target_name, unit: unit_name, field: field_name, value: entry_element.textContent });
            }
        }
    }

    // 讀取（冇登記＝null——唔做兜底猜測）
    read(target, unit, field) {
        const store_key = this.key(target, unit, field);

        // 判定：未登記 → null
        if (this.values.has(store_key) === false) {
            return null;
        }

        return this.values.get(store_key);
    }

    // 取某單位全部已登記值（[{field, value}]）
    entries_for(target, unit) {
        const entry_items = [];
        const key_prefix = target + ":" + unit + ":";

        this.values.forEach(function (value, store_key) {
            // 判定：唔屬此單位 → 跳過
            if (store_key.startsWith(key_prefix) === false) {
                return;
            }

            const field_name = store_key.slice(key_prefix.length);
            entry_items.push({ field: field_name, value: value });
        });

        return entry_items;
    }

    // 取某 target 全部值（{unit:{field:value}}——註冊方自行掃描用）
    snapshot(target) {
        const target_snapshot = {};
        const key_prefix = target + ":";

        this.values.forEach(function (value, store_key) {
            // 判定：唔屬此 target → 跳過
            if (store_key.startsWith(key_prefix) === false) {
                return;
            }

            const rest_text = store_key.slice(key_prefix.length);
            const separator_index = rest_text.lastIndexOf(":");
            const unit_name = rest_text.slice(0, separator_index);
            const field_name = rest_text.slice(separator_index + 1);

            // 判定：該單位未有格 → 先開
            if (Object.prototype.hasOwnProperty.call(target_snapshot, unit_name) === false) {
                target_snapshot[unit_name] = {};
            }

            target_snapshot[unit_name][field_name] = value;
        });

        return target_snapshot;
    }

    // 訂閱（回 unsubscribe 函數）
    subscribe(target, field, handler) {
        const subscriber = { target: target, field: field, handler: handler };

        this.subscribers.push(subscriber);

        const thisObject = this;

        return function () {
            thisObject.subscribers = thisObject.subscribers.filter(function (subscriber_item) {
                return subscriber_item !== subscriber;
            });
        };
    }

    // 通知（只派給同 target＋同 field 嘅訂閱者）
    notify(target, unit, field, value) {
        const entry = { target: target, unit: unit, field: field, value: value };

        for (let subscriber_index = 0; subscriber_index < this.subscribers.length; subscriber_index++) {
            const subscriber_item = this.subscribers[subscriber_index];

            // 判定：唔中 target 或 field → 跳過
            if (subscriber_item.target !== target) {
                continue;
            }
            if (subscriber_item.field !== field) {
                continue;
            }

            subscriber_item.handler(entry);
        }
    }

    // 值正規化（boolean → 0/1；數字字串 → Number；其餘照原值）
    normalize_value(value) {
        // 判定：布林 → 0/1
        if (value === true) {
            return 1;
        }
        if (value === false) {
            return 0;
        }

        // 判定：數字字串 → Number
        if (typeof value === "string" && value.trim() !== "" && isNaN(Number(value)) === false) {
            return Number(value);
        }

        return value;
    }
}

export const store = (function (instance) {
    return function () {

        return instance;
    };
})(new _store());
