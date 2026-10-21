// jsmods/project/act/project_list_rank_refresh.js —— 專案列排位刷新（act 層——一檔一事；主動作＝檔名）
// 職責：project_list_rank_refresh（依 data-update_time 自動重排全部專案卡（最新第一）——FLIP 全列自動調位）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_list_rank_refresh {
    constructor() {}

    // 依 data-update_time 自動重排（最新第一；無值者殿後照原序——FLIP 全列自動滑位）
    project_list_rank_refresh() {
        const j_list = jQuery(".sc_project_list");

        // Home 項唔參與排序（固定第一）
        const j_project_items = j_list.children(".sc_project_card").not(".sc_project_card--home");

        // 判定：不足兩項 → 唔動
        if (j_project_items.length < 2) {
            return;
        }

        // 現況次序（元素）＋逐項更新時間（無 attr → 空字串＝最舊）
        const current_items = [];
        const update_time_entries = [];
        j_project_items.each(function (item_index, item_element) {
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

        // FLIP ① First：量全部專案卡原位
        const item_positions = [];
        j_project_items.each(function (item_index, item_element) {
            item_positions.push({
                element: item_element,
                top: item_element.getBoundingClientRect().top,
            });
        });

        // ② Last：真重排（照目標次序逐個排返入清單——次序 SSOT＝目標次序）
        for (let item_index = 0; item_index < update_time_entries.length; item_index++) {
            const j_target_item = jQuery(update_time_entries[item_index].element);

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

export const project_list_rank_refresh = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_list_rank_refresh());
