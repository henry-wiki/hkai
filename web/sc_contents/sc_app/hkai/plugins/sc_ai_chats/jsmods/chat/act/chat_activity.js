// jsmods/chat/act/chat_activity.js —— 對話列活動刷新（act 層——緊密相關三動作同檔）
// 職責：① chat_list_status_refresh（依 chat 報告 polling_count 刷 chat_item 嘅 polling class——純 CSS 據此亮 loading 底線）
//       ② chat_list_rank_refresh（依 data-update_time 自動重排首 30 項（最新第一）——FLIP 全列自動調位）
//       ③ chat_list_sync_update_time（依 health chat 報告同步各項時間 attr＋文字——觀察器據此自動排位）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_activity {
    constructor() {}

    // 依 chat 報告逐個對話刷狀態 class（polling_count > 0 → 加 polling class；0 → 移除——時間唔喺呢度掂）
    chat_list_status_refresh(chat_reports) {
        const j_chat_items = jQuery(".sc_chat_list .sc_chat_card");

        // 全清先（重套唔會殘留）
        j_chat_items.removeClass("polling");

        // 逐筆報告（工作中數 > 0 → 對應 item 加 polling class）
        for (let report_index = 0; report_index < chat_reports.length; report_index++) {
            const chat_report_item = chat_reports[report_index];

            // 判定：冇工作中訊息 → 唔落
            if (chat_report_item.polling_count < 1) {
                continue;
            }

            const j_active_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_id='" + chat_report_item.chat_id + "']");

            j_active_item.addClass("polling");
        }

        // 狀態更新完 → 順手重排（自動調位；次序無變＝佢自己會 no-op）
        this.chat_list_rank_refresh();
    }

    // 依 data-update_time 自動重排首 30 項（最新第一；無值者殿後照原序——FLIP 全列自動滑位）
    chat_list_rank_refresh() {
        const j_list = jQuery(".sc_chat_list");

        const j_chat_items = j_list.children(".sc_chat_card");
        const rank_scope_size = 30;
        const j_rank_scope_items = j_chat_items.slice(0, rank_scope_size);

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

    // 依 health chat 報告逐項同步更新時間（attr＝Unix 秒＋時間文字——觀察器據此自動排位；唔喺清單者略過）
    chat_list_sync_update_time(chat_reports) {
        for (let report_index = 0; report_index < chat_reports.length; report_index++) {
            const chat_report_item = chat_reports[report_index];

            const j_chat_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_id='" + chat_report_item.chat_id + "']");

            // 判定：該對話唔喺清單（其他專案／未渲染）→ 略過
            if (j_chat_item.length < 1) {
                continue;
            }

            j_chat_item.attr("data-update_time", chat_report_item.last_update);
            j_chat_item.find(".sc_chat_card__updated").text(chat_report_item.last_update_text);
        }
    }
}

export const chat_activity = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_activity());
