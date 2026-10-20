// jsmods/message_window/message_window.js —— 訊息窗口域（唯一入口——卸載／還原四情況；獨立模組零侵入 message 家族）
// 職責：① 啟動編排（init——重掛掛點登記＋首掛）② 全部重掛（reset——清字典＋三觀察器重掛＋現況全掃）③ 四情況路由（清下／清上／載下／載上——units 開關逐個測）
// 幾何：卸載界＝視窗上下各外擴 100%（真 wrapper 出界＝卸載）；還原界＝上下各外擴 50%（佔位入界＝還原）——滯後區防邊界抖動
// 還原零 render：卸載前存 outerHTML 成品（含 markdown／mermaid 結果）——插返即見、零網絡、唔使再跑任何渲染
// 保高零跳動：佔位同高（卸載前量 offsetHeight）——上面移除都唔會影響觀看位置（佈局零變）
// 結構：class 單例 + export const factory（動態 import 用——零 .default）

class _message_window {
    constructor() {
        this.inited = false;

        // 卸載字典（message_id → 卸載前 outerHTML 成品——還原唯一來源）
        this.trim_map = new Map();

        // 觀察器（三支：卸載界／還原界／名冊管理）
        this.trim_observer = null;
        this.restore_observer = null;
        this.registry_observer = null;

        // 界線（rootMargin——可調；100%＝一個視窗高）
        this.trim_margin = "100% 0px 100% 0px";
        this.restore_margin = "50% 0px 50% 0px";

        // 四情況開關（逐個測——預設全開）
        this.units = {
            trim_below: true,
            trim_above: true,
            restore_below: true,
            restore_above: true,
        };
    }

    // 啟動（sc_ai_chats init 召集——一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        const thisObject = this;

        // 列表載入（每次）→ 全部重掛（清字典、觀察器重掛、現況全掃——喺現有 hook 之後登記：行喺標記還原／跳底之後）
        registry().sc_ai_chats().register_hook("load_message_list", {
            after: function () {
                thisObject.reset();
            },
        });

        this.reset();

        return this;
    }

    // 全部重掛（清字典＋三觀察器重掛＋現況全掃；列表載入後／首掛共用）
    reset() {
        const thisObject = this;
        const viewport_element = jQuery(".sc_thread__viewport")[0];

        // 清卸載字典（舊對話成品一律唔留——防跨對話誤還原）
        this.trim_map.clear();

        // 判定：容器唔存在 → 淨係清咗狀態
        if (!viewport_element) {
            return;
        }

        // 判定：瀏覽器唔支援 IntersectionObserver → 唔掛（維持原行為——零 windowing）
        if (typeof IntersectionObserver !== "function") {
            return;
        }

        // 舊觀察器先斷（重掛唔殘留）
        this.disconnect_observers();

        // 卸載界（觀察真 wrapper——離場＝卸載）
        this.trim_observer = new IntersectionObserver(function (entries) {
            thisObject.on_trim_entries(entries);
        }, {
            root: viewport_element,
            threshold: 0,
            rootMargin: this.trim_margin,
        });

        // 還原界（觀察佔位——入場＝還原）
        this.restore_observer = new IntersectionObserver(function (entries) {
            thisObject.on_restore_entries(entries);
        }, {
            root: viewport_element,
            threshold: 0,
            rootMargin: this.restore_margin,
        });

        // 名冊管理（直系加減 → 掛／除觀察——真節點同佔位交接全靠佢）
        this.registry_observer = new MutationObserver(function (mutation_records) {
            thisObject.on_dom_changed(mutation_records);
        });

        this.registry_observer.observe(viewport_element, { childList: true });

        // 現況全掃（現有直系——wrapper 入卸載界、佔位入還原界）
        this.scan_existing_children(viewport_element);
    }

    // 觀察器全斷（重掛前；未掛＝null 跳過）
    disconnect_observers() {
        // 判定：卸載界已存在 → 斷
        if (this.trim_observer) {
            this.trim_observer.disconnect();
        }

        // 判定：還原界已存在 → 斷
        if (this.restore_observer) {
            this.restore_observer.disconnect();
        }

        // 判定：名冊管理已存在 → 斷
        if (this.registry_observer) {
            this.registry_observer.disconnect();
        }

        this.trim_observer = null;
        this.restore_observer = null;
        this.registry_observer = null;
    }

    // 現況全掃（現有直系逐個歸隊）
    scan_existing_children(viewport_element) {
        const j_viewport_children = jQuery(viewport_element).children();

        // 逐個直系歸隊（真 wrapper／佔位）
        for (let index = 0; index < j_viewport_children.length; index++) {
            this.observe_node(j_viewport_children[index]);
        }
    }

    // 單節點歸隊（真 wrapper → 卸載界；佔位 → 還原界；其餘唔理）
    observe_node(node) {
        const j_node = jQuery(node);

        // 判定：真 wrapper → 入卸載界
        if (j_node.hasClass("message_wrapper")) {
            this.trim_observer.observe(node);
        }

        // 判定：佔位 → 入還原界
        if (j_node.hasClass("message_placeholder")) {
            this.restore_observer.observe(node);
        }
    }

    // 名冊回呼（直系加減：加 → 掛；減 → 除——換入換出交接自動跟）
    on_dom_changed(mutation_records) {
        // 逐筆紀錄處理
        for (let record_index = 0; record_index < mutation_records.length; record_index++) {
            this.apply_added_nodes(mutation_records[record_index].addedNodes);
            this.apply_removed_nodes(mutation_records[record_index].removedNodes);
        }
    }

    // 加咗嘅直系節點 → 掛觀察
    apply_added_nodes(added_nodes) {
        // 逐個新節點歸隊
        for (let index = 0; index < added_nodes.length; index++) {
            const added_node = added_nodes[index];

            // 判定：唔係元素節點 → 唔理（文字節點）
            if (added_node.nodeType !== 1) {
                continue;
            }

            this.observe_node(added_node);
        }
    }

    // 減咗嘅直系節點 → 除觀察（唔喺度嘅 target 除咗都安全）
    apply_removed_nodes(removed_nodes) {
        // 逐個離場節點除觀察
        for (let index = 0; index < removed_nodes.length; index++) {
            const removed_node = removed_nodes[index];

            // 判定：唔係元素節點 → 唔理
            if (removed_node.nodeType !== 1) {
                continue;
            }

            this.trim_observer.unobserve(removed_node);
            this.restore_observer.unobserve(removed_node);
        }
    }

    // 卸載界回呼（真 wrapper 離場 → 情況①清下／情況②清上）
    async on_trim_entries(entries) {
        const thisObject = this;
        const viewport_element = jQuery(".sc_thread__viewport")[0];

        // 判定：容器唔見咗（換版）→ 唔理
        if (!viewport_element) {
            return;
        }

        const viewport_rect = viewport_element.getBoundingClientRect();

        // 逐條離場項處理
        for (let entry_index = 0; entry_index < entries.length; entry_index++) {
            const intersection_entry = entries[entry_index];

            // 判定：入界（近區嚟返）→ 唔理（卸載只跟離場）
            if (intersection_entry.isIntersecting) {
                continue;
            }

            // 判定：目標已離開 DOM（早前已換走）→ 唔理
            if (!intersection_entry.target.isConnected) {
                continue;
            }

            const j_wrapper = jQuery(intersection_entry.target);

            // 判定：工作中訊息唔卸載（進行中——防斷連載）
            if (j_wrapper.attr("data-working") === "1") {
                continue;
            }

            // 離場側（真節點幾何：上緣喺可見底之下＝下方離場；下緣喺可見頂之上＝上方離場）
            const entry_is_below = intersection_entry.boundingClientRect.top >= viewport_rect.bottom;
            const entry_is_above = intersection_entry.boundingClientRect.bottom <= viewport_rect.top;

            // 判定：下方離場 → 情況①清下
            if (entry_is_below) {
                await thisObject.unload_by_unit("trim_below", j_wrapper);
                continue;
            }

            // 判定：上方離場 → 情況②清上
            if (entry_is_above) {
                await thisObject.unload_by_unit("trim_above", j_wrapper);
            }
        }
    }

    // 還原界回呼（佔位入場 → 情況③載下／情況④載上）
    async on_restore_entries(entries) {
        const thisObject = this;
        const viewport_element = jQuery(".sc_thread__viewport")[0];

        // 判定：容器唔見咗（換版）→ 唔理
        if (!viewport_element) {
            return;
        }

        const viewport_rect = viewport_element.getBoundingClientRect();

        // 逐條入場項處理
        for (let entry_index = 0; entry_index < entries.length; entry_index++) {
            const intersection_entry = entries[entry_index];

            // 判定：離場 → 唔理（還原只跟入場）
            if (!intersection_entry.isIntersecting) {
                continue;
            }

            // 判定：目標已換走（早前已還原）→ 唔理
            if (!intersection_entry.target.isConnected) {
                continue;
            }

            const j_placeholder = jQuery(intersection_entry.target);

            // 入場側（上緣喺可見底之下＝下側；其餘＝上側——含跳轉落入可見區）
            const entry_is_below = intersection_entry.boundingClientRect.top >= viewport_rect.bottom;

            // 判定：下側入場 → 情況③載下
            if (entry_is_below) {
                await thisObject.restore_by_unit("restore_below", j_placeholder);
                continue;
            }

            // 判定：上側入場 → 情況④載上
            await thisObject.restore_by_unit("restore_above", j_placeholder);
        }
    }

    // 跟開關卸載（單元關咗＝唔郁——逐個測）
    async unload_by_unit(unit_key, j_wrapper) {
        // 判定：該情況單元關咗 → 唔卸載
        if (this.units[unit_key] !== true) {
            return;
        }

        const unload_message_instance = await this.act().unload_message();
        unload_message_instance.unload_message(j_wrapper, this.trim_map);
    }

    // 跟開關還原（單元關咗＝唔郁——逐個測）
    async restore_by_unit(unit_key, j_placeholder) {
        // 判定：該情況單元關咗 → 唔還原
        if (this.units[unit_key] !== true) {
            return;
        }

        const restore_message_instance = await this.act().restore_message();
        restore_message_instance.restore_message(j_placeholder, this.trim_map);
    }
}

// 連接（出入口掛上 prototype）
_message_window.prototype.act = function () { return new _message_window_act_bridge(); };

export const message_window = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_window());

// ── 動作表（bridge——接駁 act 目錄嘅 function；每條自載）──

// act（動作——開單叫工作）
class _message_window_act_bridge {
    constructor() {}
    init() {

    }
    async unload_message()   { return (await import("./act/unload_message.js")).unload_message(); }
    async restore_message()  { return (await import("./act/restore_message.js")).restore_message(); }
}
