// jsmods/bench_modal/act/render_model_list.js —— 開場初始化（action 層——一 action 一檔）
// 職責：render_model_list（開場續行——揀左欄首個 entry 項 → 切換＋載該頁；一個 entry 都無 → 不動作）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _render_model_list {
    constructor() {}

    // 灌內容後續行（載目標 entry 頁——目標＝左欄首項；切換＋載入＋渲染喺 switch_bench_source_item 內）
    async render_model_list() {
        // 左欄 entry 按鈕（目標＝第一個——睇榜與頁面語境無關，零記憶）
        const j_side_bench_source_tab_buttons = bench_modal_instance.get_side_bench_source_tab_buttons();
        const j_first_side_bench_source_tab_button = j_side_bench_source_tab_buttons.first();

        const selected_bench_source_id = j_first_side_bench_source_tab_button.attr("data-bench_source_id");
        const selected_entry = j_first_side_bench_source_tab_button.attr("data-entry");

        // 判定：一個 entry 都無 → 不動作（空表——等用戶撳）
        if (!selected_bench_source_id) {
            return;
        }

        // 切換 entry＋載入該頁（單一入口）
        const switch_bench_source_item_instance = await bench_modal_instance.act().switch_bench_source_item();

        switch_bench_source_item_instance.switch_bench_source_item(selected_bench_source_id, selected_entry);
    }
}

export const render_model_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _render_model_list());
