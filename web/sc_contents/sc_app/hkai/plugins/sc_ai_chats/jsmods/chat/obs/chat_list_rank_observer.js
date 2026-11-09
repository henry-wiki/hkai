// jsmods/chat/obs/chat_list_rank_observer.js —— 對話清單排位觀察器（依 data-update_time 最新第一）
// 職責：訂閱 workspace_stream "chat"（對話有變）→ 合併同一批 → FLIP 全列重排（新→舊）
// 上游：chat 域 bind_events（開域一次）
// 下游：DOM（.sc_chat_list 子項重排——唔改任何資料，只排位）
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

const FLUSH_DELAY_MS = 30;

class _chat_list_rank_observer {
    constructor() {
        // 取消登記函數（未登記＝null）
        this.unsubscribe = null;

        // 排定 flush 嘅 timer（未排＝null）
        this.flush_timer = null;
    }

    // 登記（重複呼叫只登記一次）
    async init() {
        // 判定：已登記 → 直回
        if (this.unsubscribe !== null) {
            return this;
        }

        const workspace_stream_instance = await registry().sc_stream().workspace();

        this.unsubscribe = workspace_stream_instance.subscribe("chat", this.changed.bind(this));

        return this;
    }

    // 收到「對話有變」→ 合併同一批再排（連續多幀唔會連環播動畫）
    changed() {
        // 判定：已排 flush → 唔重複排
        if (this.flush_timer !== null) {
            return;
        }

        this.flush_timer = setTimeout(this._flush.bind(this), FLUSH_DELAY_MS);
    }

    // 執行重排（合併後）
    _flush() {
        this.flush_timer = null;

        this.chat_list_rank_refresh(true);
    }

    // 依 data-update_time 重排（最新第一；無值者殿後照原序——FLIP 全列自動滑位）
    // rank_all＝true：全清單參與（新插入卡可能落在第 30 項之後——唔放寬就永遠排唔上）
    chat_list_rank_refresh(rank_all) {
        const j_list = jQuery(".sc_chat_list");

        const j_chat_items = j_list.children(".sc_chat_card");
        const rank_scope_size = 30;
        const j_rank_scope_items = rank_all ? j_chat_items : j_chat_items.slice(0, rank_scope_size);

        // 判定：範圍內不足兩項 → 唔動
        if (j_rank_scope_items.length < 2) {
            return;
        }

        // 現況次序（元素）＋逐項更新時間（無 attr → 空字串＝最舊）
        const current_items = [];
        const update_time_entries = [];
        j_rank_scope_items.each(function (item_index, item_element) {
            current_items.push(item_element);
            update_time_entries.push({
                element: item_element,
                update_time: jQuery(item_element).attr("data-update_time") || "",
            });
        });

        // 目標次序：更新時間由新至舊（Y-m-d H:i:s 字串直比——同值＝穩定排序）
        update_time_entries.sort(function (entry_a, entry_b) {
            // 判定：b 新過 a → b 前
            if (entry_b.update_time > entry_a.update_time) {
                return 1;
            }

            // 判定：b 舊過 a → a 前
            if (entry_b.update_time < entry_a.update_time) {
                return -1;
            }

            return 0;
        });

        // 判定：次序無變 → 唔動（唔白播動畫）
        let is_same_order = true;
        for (let item_index = 0; item_index < current_items.length; item_index++) {
            // 判定：有任何一項唔同位 → 要重排
            if (update_time_entries[item_index].element !== current_items[item_index]) {
                is_same_order = false;
                break;
            }
        }

        // 判定：同一次序 → 收工
        if (is_same_order) {
            return;
        }

        // FLIP ① First：量全部對話項原位
        const item_positions = [];
        j_chat_items.each(function (item_index, item_element) {
            item_positions.push({
                element: item_element,
                top: item_element.getBoundingClientRect().top,
            });
        });

        // ② Last：真重排（照目標次序逐個插喺範圍外第一項之前——範圍＝全列則排尾）
        const j_anchor_item = j_chat_items.eq(rank_scope_size);
        for (let item_index = 0; item_index < update_time_entries.length; item_index++) {
            const j_target_item = jQuery(update_time_entries[item_index].element);

            // 判定：範圍外第一項存在 → 插入其前
            if (j_anchor_item.length) {
                j_target_item.insertBefore(j_anchor_item);
                continue;
            }

            j_target_item.appendTo(j_list);
        }

        // ③ Invert：逐項量新位 → 反位 transform（先重現原位）
        for (let item_index = 0; item_index < item_positions.length; item_index++) {
            const item_position = item_positions[item_index];
            const delta_y = item_position.top - item_position.element.getBoundingClientRect().top;

            item_position.element.style.transform = "translateY(" + delta_y + "px)";
        }

        // 強制 reflow（記低反位狀態——之後歸零先會觸發動畫）
        j_list[0].offsetHeight;

        // ④ Play：開 transition（class 帶）→ transform 歸零（逐項滑去新位）
        j_list.addClass("moving");

        for (let item_index = 0; item_index < item_positions.length; item_index++) {
            item_positions[item_index].element.style.transform = "";
        }

        // 收尾：動畫播完除 class（防殘留）
        setTimeout(function () {
            j_list.removeClass("moving");
        }, 320);
    }
}

export const chat_list_rank_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_list_rank_observer());
