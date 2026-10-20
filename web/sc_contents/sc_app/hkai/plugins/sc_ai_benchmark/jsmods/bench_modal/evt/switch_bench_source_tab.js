// jsmods/bench_modal/evt/switch_bench_source_tab.js —— 來源 tab 點擊綁定（evt 層——一檔一事）
// 職責：switch_bench_source_tab（.modal__tabs .modal_tab 點擊 → 切換成該來源）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _switch_bench_source_tab {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(bench_modal_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_benchmark_switch_bench_source_tab")
            .on("click.sc_ai_benchmark_switch_bench_source_tab", ".modal__tabs .modal_tab", this.switch_bench_source_tab.bind(this));
    }

    // 選來源（modal__tabs 掣——選擇過程：切換成該來源）
    async switch_bench_source_tab(event) {
        const j_element = jQuery(event.currentTarget);

        const bench_source_id = j_element.attr("data-bench_source_id");

        // spawn 取組（switch_bench_source_tab——切換過程）
        const switch_bench_source_tab_instance = await bench_modal_instance.act().switch_bench_source_tab();

        switch_bench_source_tab_instance.switch_bench_source_tab(bench_source_id);
    }
}

export const switch_bench_source_tab = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_bench_source_tab());
