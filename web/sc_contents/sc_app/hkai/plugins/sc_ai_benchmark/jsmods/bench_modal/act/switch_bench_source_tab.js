// jsmods/bench_modal/act/switch_bench_source_tab.js —— 來源 tab 切換（action 層——一 action 一檔）
// 職責：switch_bench_source_tab（切換來源：tab on 態＋重砌左欄＋載該來源首個 entry；callbacks 回報；切換過程單一入口）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _switch_bench_source_tab {
    constructor() {}

    // 切換來源（tab on 態 → 重砌左欄 → 載首個 entry；callbacks 回報——切換過程單一入口）
    async switch_bench_source_tab(bench_source_id, callbacks) {
        const thisObject = this;

        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        // 無來源（空資料）→ 不動作
        if (!bench_source_id) {
            return;
        }

        const j_modal = bench_modal_instance.get_j_modal();

        // tab on 態（來源切換列——.modal_tab）
        const j_bench_source_tab_buttons = bench_modal_instance.get_bench_source_tab_buttons();

        j_bench_source_tab_buttons.removeClass("on");
        j_bench_source_tab_buttons.filter("[data-bench_source_id='" + bench_source_id + "']").addClass("on");

        // 左欄 loading（換欄中——遮罩遮左欄）
        const loading = registry().sc_loading();
        const j_side_bench_source_list = j_modal.find("#side_bench_source_list");

        loading.start_panel_loading(j_side_bench_source_list);

        // 橋接駁（同域 req 橋——新方式）
        const fetch_bench_source_navigation_instance = await bench_modal_instance.req().fetch_bench_source_navigation();

        await fetch_bench_source_navigation_instance.fetch_bench_source_navigation(bench_source_id, {
            success: async function (response_data) {
                loading.stop_loading(j_side_bench_source_list);

                // 左欄換入（後端砌——前端只 insert）
                j_side_bench_source_list.empty();
                j_side_bench_source_list.append(response_data.html.navigation);

                // 載該來源首個 entry（同一條載入路——交 switch_bench_source_item）
                const switch_bench_source_item_instance = await bench_modal_instance.act().switch_bench_source_item();

                const j_first_side_bench_source_tab_button = bench_modal_instance.get_side_bench_source_tab_buttons().first();
                const first_entry = j_first_side_bench_source_tab_button.attr("data-entry");

                await switch_bench_source_item_instance.switch_bench_source_item(bench_source_id, first_entry);

                // 判定：有 callbacks.success → 回報成功
                if (typeof callbacks.success === "function") {
                    callbacks.success(response_data);
                }
            },
            error: function (response_data) {
                // 失敗 → 收 loading（維持舊欄——等下一撳）
                loading.stop_loading(j_side_bench_source_list);

                // 判定：有 callbacks.error → 回報失敗
                if (typeof callbacks.error === "function") {
                    callbacks.error(response_data);
                }
            }
        });
    }
}

export const switch_bench_source_tab = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_bench_source_tab());
