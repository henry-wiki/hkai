// jsmods/chat/obs/chat_state_observer.js —— chat state 變動唯一消費者
// 職責：登記 sc_state_watch 嘅 "chat" 通知 → 合併同一批 → 逐個欄位交專屬更新函數
//   新卡 → chat_item_insert；status 清空 → chat_item_remove
//   卡已存在 → 按 field 分派：msg_count／lastupdate／status 各自一個函數（唔再 fetch 成張卡）
// 上游：chat 域 bind_events（開域一次）
// 下游：chat act（insert／remove）＋ 卡上節點直接更新
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

import { sc_console } from "../../../../../jsmods/sc_console/sc_console.js";

const FLUSH_DELAY_MS = 30;

class _chat_state_observer {
    constructor() {
        // 取消登記函數（未登記＝null）
        this.unsubscribe = null;

        // 待處理（key＝chat_sid|field；value＝最後見到嘅變動）
        this.pending_changes = new Map();

        // 排定 flush 嘅 timer（未排＝null）
        this.flush_timer = null;
    }

    // 登記（重複呼叫只做一次）
    async init() {
        // 判定：已登記 → 直回
        if (this.unsubscribe !== null) {
            return this;
        }

        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const observer_instance = await state_watch_domain.observer();

        this.unsubscribe = observer_instance.subscribe("chat", this.changed.bind(this));

        return this;
    }

    // 收到一筆「有變」（同一 sid＋同一欄位只保留最後一筆）
    changed(change_entry) {
        const pending_key = change_entry.unit + "|" + change_entry.field;

        this.pending_changes.set(pending_key, change_entry);

        // 判定：已排 flush → 唔重複排
        if (this.flush_timer !== null) {
            return;
        }

        this.flush_timer = setTimeout(this._flush.bind(this), FLUSH_DELAY_MS);
    }

    // 逐筆執行（合併後）
    async _flush() {
        this.flush_timer = null;

        const pending_entries = Array.from(this.pending_changes.values());

        this.pending_changes.clear();

        for (let entry_index = 0; entry_index < pending_entries.length; entry_index++) {
            const change_entry = pending_entries[entry_index];

            await this._apply_entry(change_entry);
        }
    }

    // 按一筆變動決定動作
    async _apply_entry(change_entry) {
        const chat_sid = change_entry.unit;
        const field_name = change_entry.field;
        const value_text = String(change_entry.value);

        const chat_instance = await registry().sc_ai_chats().chat();
        const j_chat_card = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']");
        const is_card_present = j_chat_card.length > 0;
        const is_deleted = (change_entry.action === "deleted" || (field_name === "status" && value_text === ""));

        // 判定：刪除 → 拆卡
        if (is_deleted === true) {
            const chat_item_remove_instance = await chat_instance.act().chat_item_remove();

            sc_console("chat_state").log("remove " + chat_sid);

            await chat_item_remove_instance.chat_item_remove(chat_sid);

            return;
        }

        // 判定：卡唔存在 → 插入（新對話）
        if (is_card_present === false) {
            const chat_item_insert_instance = await chat_instance.act().chat_item_insert();

            sc_console("chat_state").log("insert " + chat_sid);

            await chat_item_insert_instance.chat_item_insert(chat_sid);

            return;
        }

        // 卡已存在 → 逐欄位分派（各自一個函數）
        if (field_name === "msg_count") {
            this._update_msg_count(j_chat_card, value_text);

            return;
        }

        if (field_name === "lastupdate") {
            this._update_lastupdate(j_chat_card, value_text);

            return;
        }

        if (field_name === "status") {
            this._update_status(j_chat_card, value_text);

            return;
        }
    }

    // 依 store 現值重套全部卡（清單重繪後用——避免 store 同卡面唔一致）
    async apply_current() {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const store_instance = await state_watch_domain.store();
        const chat_snapshot = store_instance.snapshot("chat");
        const chat_sids = Object.keys(chat_snapshot);

        for (let sid_index = 0; sid_index < chat_sids.length; sid_index++) {
            const chat_sid = chat_sids[sid_index];
            const chat_entry = chat_snapshot[chat_sid] || {};
            const j_chat_card = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']");

            // 判定：卡唔存在 → 跳過
            if (j_chat_card.length < 1) {
                continue;
            }

            // 判定：store 有 msg_count → 重套數字
            if (chat_entry.msg_count !== undefined) {
                this._update_msg_count(j_chat_card, String(chat_entry.msg_count));
            }

            // 判定：store 有 lastupdate → 重套時間
            if (chat_entry.lastupdate !== undefined) {
                this._update_lastupdate(j_chat_card, String(chat_entry.lastupdate));
            }

            // 判定：store 有 status → 重套狀態
            if (chat_entry.status !== undefined) {
                this._update_status(j_chat_card, String(chat_entry.status));
            }
        }
    }

    // 依 data-update_time 自動重排（最新第一；無值者殿後照原序——FLIP 全列自動滑位）
    // rank_all＝true：全清單參與（新插入卡可能落在第 30 項之後——唔放寬就永遠排唔上）
    chat_list_rank_refresh(rank_all) {
        const j_list = jQuery(".sc_chat_list");

        const j_chat_items = j_list.children(".sc_chat_card");
        const rank_scope_size = 30;
        const j_rank_scope_items = (rank_all === true) ? j_chat_items : j_chat_items.slice(0, rank_scope_size);

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

    // 更新訊息數（卡上數字——唯一住處）
    _update_msg_count(j_chat_card, value_text) {
        const j_msg_count = j_chat_card.find(".sc_chat_card__msg_count");

        j_msg_count.attr("data-count", value_text);
        j_msg_count.find("b").text(value_text);

        sc_console("chat_state").log("msg_count=" + value_text);
    }

    // 更新最後更新時間（顯示文字按相對時間階梯）
    _update_lastupdate(j_chat_card, value_text) {
        const j_updated = j_chat_card.find(".sc_chat_card__updated");
        const updated_text = this._format_relative_time(value_text);

        j_chat_card.attr("data-update_time", value_text);
        j_updated.text(updated_text);

        sc_console("chat_state").log("lastupdate=" + value_text + " -> " + updated_text);

        // 時間一變 → 即時重排（最新第一）
        this.chat_list_rank_refresh();
    }

    // 更新狀態（data-status＋歸檔樣式 class）
    _update_status(j_chat_card, value_text) {
        j_chat_card.attr("data-status", value_text);

        // 判定：歸檔 → 加歸檔樣式
        if (value_text === "archived") {
            j_chat_card.addClass("sc_chat_card--archived");
        } else {
            j_chat_card.removeClass("sc_chat_card--archived");
        }

        sc_console("chat_state").log("status=" + value_text);
    }

    // 相對時間（12s／5m／3h／2d／1w／6mo／2y——同後端同一階梯）
    _format_relative_time(date_text) {
        const target_time = new Date(String(date_text).replace(" ", "T"));
        const elapsed_seconds = Math.floor((Date.now() - target_time.getTime()) / 1000);

        // 判定：未夠 1 分鐘 → 秒
        if (elapsed_seconds < 60) {
            return elapsed_seconds + "s";
        }

        const elapsed_minutes = Math.floor(elapsed_seconds / 60);

        // 判定：未夠 1 小時 → 分
        if (elapsed_minutes < 60) {
            return elapsed_minutes + "m";
        }

        const elapsed_hours = Math.floor(elapsed_minutes / 60);

        // 判定：未夠 1 日 → 時
        if (elapsed_hours < 24) {
            return elapsed_hours + "h";
        }

        const elapsed_days = Math.floor(elapsed_hours / 24);

        // 判定：未夠 1 週 → 日
        if (elapsed_days < 7) {
            return elapsed_days + "d";
        }

        const elapsed_weeks = Math.floor(elapsed_days / 7);

        // 判定：未夠 ~6 個月 → 週
        if (elapsed_days < 180) {
            return elapsed_weeks + "w";
        }

        const elapsed_months = Math.floor(elapsed_days / 30);

        // 判定：未夠 2 年 → 月
        if (elapsed_days < 730) {
            return elapsed_months + "mo";
        }

        const elapsed_years = Math.floor(elapsed_days / 365);

        return elapsed_years + "y";
    }
}

export const chat_state_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_state_observer());
