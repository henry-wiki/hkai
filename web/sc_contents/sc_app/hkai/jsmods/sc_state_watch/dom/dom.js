// jsmods/sc_state_watch/dom/dom.js —— 監視器自有資料容器（三層）
// 基礎：<target>/snapshot.json（target → unit → field）
// 職責：把 entry 投影去 .sc_state_watch > [data-target] > [data-unit][data-field]
// 上游：state_watch/act/write（值有變才呼叫）
// 下游：無（只寫自己容器；唔碰任何外部節點）

const ROOT_SELECTOR = ".sc_state_watch";

class _dom {
    constructor() {}

    // 確保根容器存在（開頁即建——即使零變化）
    ensure_root() {
        return this._ensure_root_element();
    }

    // 確保 target 層存在（開頁即建空殼——即使零變化）
    ensure_target(target_name) {
        const root_element = this._ensure_root_element();

        return this._ensure_target_element(root_element, target_name);
    }

    // 投影一項（新節點：先寫值後插入＝一次 mutation；既有：值變才寫）
    project(entry) {
        const root_element = this._ensure_root_element();
        const target_element = this._ensure_target_element(root_element, entry.target);
        const next_text = String(entry.value);
        const entry_element = this._find_entry_element(target_element, entry.unit, entry.field);

        // 判定：新節點 → 建好（值先寫）再插入
        if (entry_element === null) {
            this._create_entry_element(target_element, entry.unit, entry.field, next_text);

            this._log_polling(entry.target, entry.unit, entry.field, next_text, target_element);

            return;
        }

        // 判定：文字相同 → 唔寫（唔觸發 observer）
        if (entry_element.textContent !== next_text) {
            entry_element.textContent = next_text;

            this._log_polling(entry.target, entry.unit, entry.field, next_text, target_element);
        }
    }

    // polling 寫入記錄（特別時間戳，方便追證）
    _log_polling(target_name, unit_name, field_name, next_text, target_element) {
        // 判定：非 polling → 唔記
        if (target_name !== "polling") {
            return;
        }

        sc_console("state_watch", "dom").log(unit_name + "/" + field_name + "=" + next_text + " target_children=" + target_element.children.length);
    }

    // 取根容器（冇就建立——監視器自己嘅節點）
    _ensure_root_element() {
        const root_element = document.querySelector(ROOT_SELECTOR);

        // 判定：已存在 → 直接用
        if (root_element !== null) {
            return root_element;
        }

        const new_root_element = document.createElement("div");

        new_root_element.className = "sc_state_watch";
        new_root_element.hidden = true;

        document.body.appendChild(new_root_element);

        return new_root_element;
    }

    // 取 target 層（冇就建立）
    _ensure_target_element(root_element, target) {
        const target_selector = "[data-target='" + target + "']";
        const target_element = root_element.querySelector(target_selector);

        // 判定：已存在 → 直接用
        if (target_element !== null) {
            return target_element;
        }

        const new_target_element = document.createElement("div");

        new_target_element.setAttribute("data-target", target);

        root_element.appendChild(new_target_element);

        return new_target_element;
    }

    // 搵 entry 層（冇＝null）
    _find_entry_element(target_element, unit, field) {
        const entry_selector = "[data-unit='" + unit + "'][data-field='" + field + "']";

        return target_element.querySelector(entry_selector);
    }

    // 建 entry 層（值先寫、後插入——只產生一個 mutation）
    _create_entry_element(target_element, unit, field, text_value) {
        const new_entry_element = document.createElement("div");

        new_entry_element.setAttribute("data-unit", unit);
        new_entry_element.setAttribute("data-field", field);
        new_entry_element.textContent = text_value;

        target_element.appendChild(new_entry_element);

        return new_entry_element;
    }
}

export const dom = (function (instance) {
    return function () {

        return instance;
    };
})(new _dom());
