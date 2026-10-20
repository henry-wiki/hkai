// jsmods/bench_modal/evt/switch_bench_source_item.js —— 左欄 bench_source 按鈕點擊綁定（evt 層——一檔一事）
// 職責：switch_bench_source_item（.side_bench_source_tab_button 點擊 → 切換右邊成該 entry 嗰頁）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _switch_bench_source_item {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(bench_modal_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_benchmark_switch_bench_source_item")
            .on("click.sc_ai_benchmark_switch_bench_source_item", ".side_bench_source_tab_button", this.switch_bench_source_item.bind(this));
    }

    // 選 bench_source（左欄按鈕——選擇過程：切換右邊頁）
    async switch_bench_source_item(event) {
        const j_element = jQuery(event.currentTarget);

        const bench_source_id = j_element.attr("data-bench_source_id");
        const entry = j_element.attr("data-entry");

        // spawn 取組（switch_bench_source_item——切換過程）
        const switch_bench_source_item_instance = await bench_modal_instance.act().switch_bench_source_item();

        switch_bench_source_item_instance.switch_bench_source_item(bench_source_id, entry);
    }
}

export const switch_bench_source_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_bench_source_item());
