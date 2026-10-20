// jsmods/callback/status_item_render.js —— status item 渲染讀寫（callback 層——一檔一事）
// 職責：status_item_render（item 取得／文字／顏色／隱藏／tooltip／狀態鍵套用——DOM 寫入單一 writer）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _status_item_render {
    constructor() {
        // item states 定義（server SSOT——由第一條 .sc_statusbar 的 data-item-states 延遲載入）
        this.state_configs = null;
    }

    // 取得 item 元素（不存在回 null——plugin 未渲染時安全跳過）
    get_item(item_id) {
        // 狀態鍵（sb_xxx）→ 新 class 契約（.sc_statusbar__item--xxx）
        const item_class = "sc_statusbar__item--" + item_id.replace("sb_", "");
        const j_item = document.querySelector("." + item_class);
        return j_item;
    }

    // 設定 item 文字（優先寫入 plugin 產出的內層 .sc-text；無內層才寫 item 本身）
    set_item(item_id, text) {
        const j_item = this.get_item(item_id);
        if (!j_item) {
            return;
        }

        const j_text = j_item.querySelector(".sc-text");
        if (j_text) {
            j_text.textContent = text;
            return;
        }
        j_item.textContent = text;
    }

    // 設定 item 顏色狀態（warn／ok／busy——先清後加，同時只允許一種）
    set_item_class(item_id, class_name) {
        const j_item = this.get_item(item_id);
        if (!j_item) {
            return;
        }

        j_item.classList.remove("warn");
        j_item.classList.remove("ok");
        j_item.classList.remove("busy");
        j_item.classList.remove("bad");

        if (class_name !== "") {
            j_item.classList.add(class_name);
        }
    }

    // 顯示／隱藏 item
    set_item_hidden(item_id, hidden) {
        const j_item = this.get_item(item_id);
        if (!j_item) {
            return;
        }

        if (hidden) {
            j_item.style.display = "none";
            return;
        }
        j_item.style.display = "";
    }

    // 設定 item tooltip（title 屬性——hover 顯示）
    set_item_tooltip(item_id, text) {
        const j_item = this.get_item(item_id);
        if (!j_item) {
            return;
        }
        j_item.title = text;
    }

    // 依狀態鍵更新 item（外部模組推值——單一 writer；文字/顏色由 server states 定義決定）
    // 用法：registry().sc_ai_status_bar().set_state("sb_conn", "connected")
    set_state(item_id, state_key) {
        const state_configs = this.get_state_configs();
        const item_states = state_configs[item_id];
        if (!item_states) {
            return;
        }

        const state = item_states[state_key];
        if (!state) {
            return;
        }

        let state_text = state.text;
        if (!state_text) {
            state_text = "";
        }

        let state_class = state.class;
        if (!state_class) {
            state_class = "";
        }

        this.set_item(item_id, state_text);
        this.set_item_class(item_id, state_class);
        this.set_item_hidden(item_id, state.hidden === true);
    }

    // 取得 item states 定義（延遲解析第一條 status bar 的 data-item-states——server SSOT）
    get_state_configs() {
        if (this.state_configs) {
            return this.state_configs;
        }

        const j_statusbar = document.querySelector(".sc_statusbar[data-item-states]");
        if (!j_statusbar) {
            this.state_configs = {};
            return this.state_configs;
        }

        let states_json = j_statusbar.dataset.itemStates;
        if (!states_json) {
            states_json = "{}";
        }

        let parsed_states = {};
        try {
            parsed_states = JSON.parse(states_json);
        } catch (parse_error) {
            parsed_states = {};
        }

        this.state_configs = parsed_states;
        return this.state_configs;
    }
}

export const status_item_render = (function (instance) {
    return function () {

        return instance;
    };
})(new _status_item_render());
